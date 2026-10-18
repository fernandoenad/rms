# RMS Applicant Portal — Staging Acceptance Checklist

**Scope:** Draft PR #46 applicant portal; run only in isolated staging with synthetic applicant records. **Do not run destructive tests on production records.**

## Preconditions

- [ ] Record current branch, commit SHA, migration status and a restorable database backup.
- [ ] Separate staging `.env`, test Google OAuth client, HTTPS callback URLs, session domain, queue worker and application URL configured.
- [ ] Verify staff and applicant Google redirect URIs separately and ensure Google OAuth state validation works in a real browser.
- [ ] Create two unrelated Google test accounts, one verified existing staff account, a pre-authorized HR reviewer, and sample recruitment vacancies.
- [ ] Confirm the old external reader of `applications` can still consume existing and newly created application rows without schema changes.

## Applicant journey

- [ ] New verified Google account signs in and receives an applicant profile without staff role.
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
