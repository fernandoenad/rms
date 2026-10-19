"""Regression tests for the read-only, fail-closed UAT gate."""
import importlib.util
import re
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location("applicant_uat_gate", ROOT / "scripts/applicant_uat_gate.py")
gate = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(gate)

SHA = "a" * 40
URL = "https://github.com/fernandoenad/rms/actions/runs/12345678"


def complete_fixture():
    template = (ROOT / "docs/applicant-portal-uat-results.md").read_text(encoding="utf-8")
    for label, value in {
        "Test environment": "Isolated staging",
        "Branch / exact commit SHA": SHA,
        "CI-verified commit SHA": SHA,
        "Passing CI run URL": URL,
        "Test date and timezone": "2026-10-19 11:30 Asia/Manila",
        "Tester": "Synthetic UAT Reviewer",
        "Database backup reference (staging only)": "sanitized-backup-ticket",
        "HR reviewer / date": "Authorized HR Reviewer / 2026-10-19",
        "Technical reviewer / date": "Technical Reviewer / 2026-10-19",
        "Decision": gate.APPROVED,
    }.items():
        template = re.sub(
            r"(^\*\*" + re.escape(label) + r":\*\*)[^\n]*",
            lambda match: match.group(1) + " " + value,
            template, count=1, flags=re.M
        )
    for item in gate.REQUIRED_CASES:
        pattern = r"(^\|\s*" + re.escape(item) + r"\s*\|[^\n]*?\|)\s*NOT RUN\s*\|\s*\|"
        template, changes = re.subn(
            pattern, lambda m: m.group(1) + " PASS | sanitized-evidence-" + item + " |",
            template, count=1, flags=re.M
        )
        if changes != 1:
            raise AssertionError(f"Missing fixture case {item}")
    start, sep, acceptance = template.partition("## Acceptance decision")
    acceptance = acceptance.replace("- [ ]", "- [x]")
    return start + sep + acceptance


class ApplicantUatGateTests(unittest.TestCase):
    def test_blank_template_is_never_release_ready(self):
        template = (ROOT / "docs/applicant-portal-uat-results.md").read_text(encoding="utf-8")
        failures = gate.validate(template, SHA)
        self.assertTrue(failures)
        self.assertTrue(any("OAUTH-01" in f for f in failures))
        self.assertTrue(any("Decision" in f for f in failures))

    def test_fully_documented_fixture_passes_gate_without_claiming_real_acceptance(self):
        self.assertEqual([], gate.validate(complete_fixture(), SHA))

    def test_fails_when_a_single_case_has_no_evidence(self):
        fixture = complete_fixture().replace(
            "PASS | sanitized-evidence-SESSION-03 |", "PASS | |", 1
        )
        issues = gate.validate(fixture, SHA)
        self.assertTrue(any("SESSION-03: PASS requires" in f for f in issues))

    def test_fails_when_staging_commit_is_not_checked_out_commit(self):
        fixture = complete_fixture().replace(
            f"**Branch / exact commit SHA:** {SHA}",
            "**Branch / exact commit SHA:** " + ("b" * 40), 1
        )
        self.assertTrue(any("checked-out code" in f for f in gate.validate(fixture, SHA)))

    def test_fails_when_ci_commit_is_not_candidate(self):
        fixture = complete_fixture().replace(
            f"**CI-verified commit SHA:** {SHA}",
            "**CI-verified commit SHA:** " + ("b" * 40), 1
        )
        self.assertTrue(any("CI-verified commit SHA: does not match" in f
                            for f in gate.validate(fixture, SHA)))

    def test_fails_for_unsigned_acceptance_even_when_every_case_passes(self):
        fixture = complete_fixture().replace("- [x] ", "- [ ] ", 1)
        self.assertTrue(any("Acceptance decision check 1" in f
                            for f in gate.validate(fixture, SHA)))

    def test_fails_if_final_release_decision_still_not_approved(self):
        fixture = complete_fixture().replace(
            "**Decision:** " + gate.APPROVED, "**Decision:** NOT APPROVED", 1
        )
        self.assertTrue(any("Decision: not approved" in f
                            for f in gate.validate(fixture, SHA)))

    def test_fails_when_required_scenario_removed(self):
        fixture = "\n".join(line for line in complete_fixture().splitlines()
                            if not line.startswith("| SESSION-04 |"))
        self.assertTrue(any("SESSION-04: missing" in f
                            for f in gate.validate(fixture, SHA)))

    def test_fails_on_duplicate_case_instead_of_silently_accepting(self):
        fixture = complete_fixture()
        row = next(line for line in fixture.splitlines() if line.startswith("| APP-01 |"))
        fixture = fixture.replace(row, row + "\n" + row, 1)
        self.assertTrue(any("APP-01: duplicate" in f
                            for f in gate.validate(fixture, SHA)))

    def test_fails_on_non_github_ci_link(self):
        fixture = complete_fixture().replace(URL, "https://invalid.example/runs/1", 1)
        self.assertTrue(any("Passing CI run URL" in f
                            for f in gate.validate(fixture, SHA)))


if __name__ == "__main__":
    unittest.main()
