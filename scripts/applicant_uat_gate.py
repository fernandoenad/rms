#!/usr/bin/env python3
"""Read-only RMS applicant UAT release gate.

Never fills in manual UAT or queries production. Checks whether the locally
completed, sanitized worksheet contains the evidence needed for release review.
"""
import argparse
import re
import subprocess
from pathlib import Path

REQUIRED_CASES = (
    "HOME-01", "NAV-01", "PROFILE-01",
    "OAUTH-01", "OAUTH-02", "SWITCH-01", "SWITCH-02",
    "APP-01", "APP-02",
    "CLAIM-01", "CLAIM-02", "CLAIM-03", "CLAIM-04", "CLAIM-05", "CLAIM-06", "CLAIM-07",
    "ASSESS-01", "ASSESS-02",
    "SESSION-01", "SESSION-02", "SESSION-03", "SESSION-04", "SESSION-05",
    "LEGACY-01", "MOBILE-01", "OPS-01", "ROLLBACK-01",
)
REQUIRED_FIELDS = (
    "Test environment",
    "Branch / exact commit SHA",
    "CI-verified commit SHA",
    "Passing CI run URL",
    "Test date and timezone",
    "Tester",
    "Database backup reference (staging only)",
    "HR reviewer / date",
    "Technical reviewer / date",
)
APPROVED = "APPROVED FOR NEXT RELEASE STAGE"
CI_URL = re.compile(r"https://github\.com/fernandoenad/rms/actions/runs/[0-9]+/?")
FULL_SHA = re.compile(r"[0-9a-f]{40}")
CASE_ID = re.compile(r"[A-Z]+-[0-9]{2}")
PLACEHOLDER = re.compile(r"^(_+|TBD|TBA|NOT RUN|NOT APPLICABLE|N/A|PENDING|NONE|-)$", re.I)


def value_for(text, label):
    pattern = r"^\*\*" + re.escape(label) + r":\*\*\s*(.*?)\s*$"
    match = re.search(pattern, text, re.M)
    return match.group(1).strip() if match else ""


def missing(value):
    return not value or bool(PLACEHOLDER.fullmatch(value))


def validate(text, sha):
    problems = []
    rows = {}
    table_started = False
    for line in text.splitlines():
        if line.startswith("| ID | Scenario | Expected result | Status |"):
            table_started = True
            continue
        if not table_started:
            continue
        if not line.startswith("|"):
            if rows:
                break
            continue
        cells = [cell.strip().replace(r"\|", "|")
                 for cell in re.split(r"(?<!\\)\|", line.strip("|"))]
        if not cells or not CASE_ID.fullmatch(cells[0]):
            continue
        key = cells[0]
        if key in rows:
            problems.append(f"{key}: duplicate UAT case")
            continue
        if len(cells) != 5:
            problems.append(f"{key}: expected five worksheet columns")
            continue
        rows[key] = cells

    for key in REQUIRED_CASES:
        if key not in rows:
            problems.append(f"{key}: missing from worksheet")
            continue
        status = rows[key][3]
        evidence = rows[key][4]
        if status != "PASS":
            problems.append(f"{key}: status is {status or 'blank'} (requires PASS)")
        elif missing(evidence):
            problems.append(f"{key}: PASS requires a sanitized evidence reference")
    for key in sorted(set(rows) - set(REQUIRED_CASES)):
        problems.append(f"{key}: unrecognized UAT case; review checklist version")

    for label in REQUIRED_FIELDS:
        val = value_for(text, label)
        if missing(val):
            problems.append(f"{label}: missing")
    candidate = value_for(text, "Branch / exact commit SHA")
    ci_sha = value_for(text, "CI-verified commit SHA")
    if candidate and not FULL_SHA.fullmatch(candidate):
        problems.append("Branch / exact commit SHA: use a full 40-character commit SHA")
    if ci_sha and not FULL_SHA.fullmatch(ci_sha):
        problems.append("CI-verified commit SHA: use a full 40-character commit SHA")
    if FULL_SHA.fullmatch(candidate or "") and candidate != sha:
        problems.append("Branch / exact commit SHA: does not match the checked-out code")
    if FULL_SHA.fullmatch(ci_sha or "") and ci_sha != sha:
        problems.append("CI-verified commit SHA: does not match the checked-out code")
    url = value_for(text, "Passing CI run URL")
    if url and not CI_URL.fullmatch(url):
        problems.append("Passing CI run URL: expected a link to this repository's GitHub Actions run")

    acceptance = text.partition("## Acceptance decision")[2]
    required_checks = [line for line in acceptance.splitlines() if line.startswith("- [")]
    if len(required_checks) != 6:
        problems.append("Acceptance decision: expected six explicit release sign-off checks")
    for index, line in enumerate(required_checks, 1):
        if not line.startswith("- [x]") and not line.startswith("- [X]"):
            problems.append(f"Acceptance decision check {index}: not signed off")

    if value_for(text, "Decision") != APPROVED:
        problems.append("Decision: not approved for next release stage")
    return problems


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--worksheet", type=Path,
                        default=Path("docs/applicant-portal-uat-results.md"))
    parser.add_argument("--sha", default=None,
                        help="Candidate full Git commit SHA; defaults to current checkout")
    args = parser.parse_args()
    if args.sha:
        sha = args.sha.lower()
    else:
        try:
            sha = subprocess.check_output(
                ["git", "rev-parse", "HEAD"], text=True, stderr=subprocess.DEVNULL
            ).strip().lower()
        except (OSError, subprocess.CalledProcessError):
            parser.error("Cannot determine git SHA; provide --sha with a full commit hash")
    if not FULL_SHA.fullmatch(sha):
        parser.error("--sha must be a full 40-character Git commit SHA")
    try:
        worksheet = args.worksheet.read_text(encoding="utf-8")
    except OSError:
        parser.error("Cannot read the specified UAT worksheet")

    failures = validate(worksheet, sha)
    if failures:
        print(f"BLOCKED: {len(failures)} applicant portal UAT release gate issue(s)")
        for problem in failures:
            print("- " + problem)
        print("Do not merge or deploy. Use real manual staging evidence, not CI results alone.")
        return 1

    print(f"READY FOR RELEASE REVIEW: {len(REQUIRED_CASES)} manual cases documented PASS")
    print("Worksheet is complete; verify signers, evidence, and live CI independently.")
    print("This command does NOT merge, deploy, or independently verify attached evidence.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
