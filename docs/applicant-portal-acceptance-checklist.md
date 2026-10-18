# RMS Applicant Portal — Staging Acceptance Checklist

**Scope:** Draft PR #46 applicant portal; run only in isolated staging with synthetic applicant records. **Do not run destructive tests on production records.**

## Automated verification evidence (2026-10-18)

- [x] GitHub Actions: `Applicant Portal Full Suite` completed successfully on PHP 8.2 and PHP 8.4, run [39103144540](https://github.com/fernandoenad/rms/actions/runs/39103144540). This covers the suite at commit `8c099339` (before later changes).
- [x] GitHub Actions: `Assessment Regression Suite` and `Assessment Center CI` completed successfully at commit `8c099339`.
- [x] Full PHPUnit on PHP 8.2 and PHP 8.4, Assessment Regression Suite and Assessment Center CI passed for commit `b5eb7497` (before the runbook documentation change), including [full suite run 39112374461](https://github.com/fernandoenad/rms/actions/runs/39112374461).
- [ ] Reconfirm the suite for the final candidate commit. Passing CI on older code commits does not certify later changes.
- [x] Full PHPUnit on PHP 8.2 and PHP 8.4, plus disposable secondary-MySQL assigned-station detail rendering, passed at commit `da6d9215`: [run 39119075494](https://github.com/fernandoenad/rms/actions/runs/39119075494). This confirms synthetic route rendering, **not** the live HRMIS station data or production external reader.
- [ ] Complete live-browser Google OAuth, staff/applicant switching, recruitment submission, HR validation, assessment flows, and external-reader tests. Automated tests do **not** replace these checks.

### Evidence capture for manual UAT

For each manual scenario below, record tester, local date/time, environment/URL, test account ID (non-sensitive), result (pass/fail/blocked), screenshot or log reference, and a defect ID if applicable. Do not include OAuth secrets or personally identifiable application records in screenshots.

**Step-by-step execution:** [Staging UAT Runbook](applicant-portal-staging-runbook.md). Evidence in this checklist remains unchecked until performed in the real staging environment.

## Preconditions

- [ ] Record current branch, commit SHA, migration status and a restorable database backup.
- [ ] Separate staging `.env`, test Google OAuth client, HTTPS callback URLs, session domain, queue worker and application URL configured.
- [ ] Verify staff and applicant Google redirect URIs separately and ensure Google OAuth state validation works in a real browser.
- [ ] Create two unrelated Google test accounts, one verified existing staff account, a pre-authorized HR reviewer, and sample recruitment vacancies.
- [ ] Confirm the old external reader of `applications` can still consume existing and newly created application rows without schema changes.

## Applicant journey

- [ ] New verified Google account signs in and receives an applicant profile without staff role.
- [ ] Guest who selects an open vacancy before Google sign-in returns to that vacancy after authentication.
- [ ] Guest whose selection is older than 30 minutes returns to dashboard with no application created.
- [ ] Guest whose vacancy closes during Google sign-in returns to dashboard and cannot submit.
- [ ] Unverified or invalid Google identity is rejected.
- [ ] New applicant opens open vacancy deep link and is prompted to finish profile.
- [ ] Profile validation errors are shown; valid profile saves, then vacancy review shows exactly the saved values.
- [ ] Submission creates one `applications` row and one applicant ownership link; applicant dashboard shows the new code.
- [ ] Repeat submit does not create a second application; repeat review opens existing application.
- [ ] Closing vacancy blocks new applications but does not block access to a previously submitted one.
- [ ] Editing master profile does not rewrite previously submitted application snapshots.

## Staff coexistence

- [ ] Pre-authorized active staff Google login succeeds and preserves role.
- [ ] Staff switches to applicant portal using the same authenticated user; returns to staff portal without another user being created.
- [ ] Unregistered, inactive, and applicant-only accounts cannot access staff pages.
- [ ] A Google subject linked to another user cannot be rebound by matching an email.
- [ ] Staff cannot approve their own historical claim.

## Historical claims

- [ ] Verified Google email discovers only unowned historical applications matching that email.
- [ ] Different-email claim request does not grant access before HR approval.
- [ ] HR rejects a claim with a reason and an audit entry.
- [ ] HR requests clarification; applicant sees the instruction, and still cannot access the application.
- [ ] HR verifies identity by an independent method and approves; ownership becomes available.
- [ ] When two applicants claim the same record, one approval closes other pending claims without revealing the accepted claimant.
- [ ] Already finalized claims cannot be re-approved or transferred.

## Assessment isolation

- [ ] An owner can start and resume assigned written and skills attempts according to schedule.
- [ ] A non-owner cannot view attempts, fetch time/review state, autosave answers, upload responses, record events or submit another applicant's attempt.
- [ ] Timed-out attempts finalize consistently without losing prior saved work.
- [ ] Paused assessments and equivalent-set locks behave as designed.
- [ ] Submission and score release continue to respect the configured manual review policy.

## Operational exit criteria

- [ ] Execute `./vendor/bin/phpunit --testdox` from the feature branch and record full output.
- [ ] Verify application, session, mail, queue and Google OAuth error logs show no unexplained errors.
- [ ] Confirm browser workflow on desktop and mobile, including back navigation, CSRF expiry and sign-out.
- [ ] Confirm no unexpected staff privilege elevation and no cross-account data access.
- [ ] Capture signed HR/UAT acceptance, known defects, rollback steps and external-reader compatibility results.
- [ ] Keep PR **draft and unmerged** until all critical/high defects are resolved and acceptance criteria are signed off.
