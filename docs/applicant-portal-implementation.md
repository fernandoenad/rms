# Applicant Portal implementation status

Branch: `feature/applicant-portal-foundation`

## Invariants
- Never alter the `applications` table schema for this feature.
- Never rewrite historical application personal information from master profile edits or claims.
- Applicant ownership lives only in `applicant_application_links` (one link per application).
- New applications copy current profile values at submission and create the ownership link in the same transaction.
- Matching verified Google emails may auto-link eligible, unowned legacy applications.
- Different-email applications require an HR-reviewed claim (code + historical email only identifies a record).
- Staff/applicant portal switching must not grant additional permissions.
- All applicant-facing assessment and inquiry endpoints must use ownership policies before cutover.

## Implemented in foundation
- Isolated migration creating applicant_profiles, oauth_identities, applicant_application_links, application_claim_requests, applicant_audit_logs.
- Basic Eloquent models.
- Historical email-match ownership service (must be called only after verified Google OAuth).
- Claim request, HR approval/rejection business service (must be exposed only behind an HR permission gate).
- Legacy profile-to-application field mapper.
- Initial migration/mapper tests.

## Still required before any production rollout
1. Google OAuth callback with state verification and safe handling of existing staff accounts.
2. Authenticated profile onboarding, staff/applicant portal switching and UI.
3. Applicant claim endpoints, HR interface with evidence review and appropriate permission checks.
4. Applicant application submission transaction with vacancy validation, duplicate prevention, profile mapping and link.
5. Replace all guest_email access checks in application, exam, skills and inquiry routes.
6. Confirm exact protected schema contract and third-party read behavior.
7. Add comprehensive tests for access control, link races, claim disputes, preservation of legacy data and existing assessment workflows.
8. Evaluate Google-matching legacy email ownership risk; do not assume emails originally submitted to RMS were verified.
9. Run migrations and tests in staging; do not enable features in production prematurely.

This branch is deliberately a non-routed foundation; it does not change the existing login or public applicant behavior.

## October 18 integration pass
- Added stateful applicant Google OAuth endpoint and callback. Existing staff OAuth flow remains intact.
- Added authenticated applicant dashboard, master profile edit/update, and historical claim request routes.
- Added HR claim review controller and approval/rejection interface restricted to existing admin middleware.
- Added new vacancy submission service that copies profile fields into the existing applications schema with an ownership link in one transaction.
- Added read-only new application review view and a dashboard vacancy navigation link.

## Important limitations after this pass
- The public guest vacancy apply route still displays the legacy applicant form; it must be redirected into the new authenticated review workflow before launch.
- Legacy guest_email-based application, inquiry and assessment authorization has NOT been replaced; the insecure paths remain in the current app until a coordinated cutover.
- Staff-to-applicant switching is available from the applicant dashboard only in this pass; add staff navigation entry and refine role checks.
- Google identity/account linking and existing active staff protections require integration/regression tests.
- HR approval UI needs more robust identity evidence and conflict review before production use.
- Automated tests and migrations have not been executed by this GitHub connector session.
- This draft PR must NOT be merged or deployed as a finished applicant portal.

## Phase: legacy applicant access cutover (October 18)
- Added ApplicationOwnershipService to check applicant_application_links against authenticated user profile.
- Protected guest application detail, regular inquiry, AI inquiry, written exam, answer-autosave and skill-test access using ownership checks.
- Wrapped applicant-specific legacy routes in auth middleware; changed email lookup to dashboard redirects.
- Redirected the public vacancy Apply entry to the authenticated profile review workflow.
- Added applicant/staff portal navigation to the public layout and safe nullable staff-role middleware handling.
- Added focused authorization and historical-value regression test source.

### Deployment blockers and follow-up
- This is a code commit, NOT an executed Laravel test run; PHP migrations and runtime configuration have not been checked in staging.
- Legacy application UI and live exam sessions need regression validation; existing guest-email sessions will no longer authorize assessment attempts.
- Applicant Google OAuth provisioning, staff identity mapping and email matching require security testing for pre-existing unverified legacy email values.
- Add an HR evidence verification checklist, link conflict handling and audit writes before treating manual approvals as production-ready.
- Audit every applicant-facing endpoint and linked documents for remaining direct-ID access paths.
- Confirm and test the external reader's expectations of the protected applications table.
- Verify the Google OAuth callback URL has been added to the Google Cloud OAuth client.

## Security hardening pass — October 18
- Existing staff accounts are no longer silently attached to applicant Google OAuth identities solely by matching email.
- Existing Google identity subject remains the durable identity key.
- Historical discovery failures are reported rather than invalidating an otherwise successful applicant session.
- Applicant profile updates and HR claim decisions now insert applicant_audit_logs rows.
- Added smoke tests for unauthenticated access, authenticated profile editing and claim input validation.
- Existing applications schema remains untouched.

### Outstanding release-critical work
- Build a secure, explicit migration for **existing staff** to link their Google subject to their pre-existing user record. The current protection rejects unmatched staff identities; portal switching is not fully ready for staff with pre-existing accounts.
- Confirm Google provider returns the verified email claim with the configured Socialite driver.
- Add authorization policies and test all staff/admin routes, beyond the claim-review queue.
- HR identity verification remains a manual judgment requiring documented evidence and controls.
- Run PHP syntax, route:list, Laravel migrations and PHPUnit in a staging environment; connector commits alone do not execute tests.
- Integration-test the previous in-progress assessment sessions, external application read contract, and database compatibility before merging.

## Staff account Google identity linking phase
- Added `/auth/staff/link-google` which requires an authenticated, active staff session.
- Added a separate Google callback, protected by session-bound staff user ID, Socialite state, Google subject, verified email, and staff email equality.
- Existing staff accounts are not silently adopted by applicant onboarding based only on matching email.
- Once staff OAuth binding succeeds, the current session can use the applicant dashboard and switch to the staff dashboard without a second sign-in.
- Applicant and staff Google entry points explicitly set their OAuth redirect URLs. Register both callback addresses in Google Cloud Console.
- Added starter route guard tests.

### Important limitations
- A staff member must initially authenticate through the existing staff login before explicitly linking Google; this is a migration step, not a recurring second login.
- Verify whether the registered staff email is the same address returned by Google; staff email changes and delegated accounts need administrative reconciliation.
- Callback tests using mocked Google user responses, database concurrency tests, and staged live Google OAuth tests are still required.
- No PHP runtime, database migrations, or automated tests were executed through the GitHub integration.
- The draft PR is not approved for production deployment.

## Integration code review: October 18, 2026
- Move ownership checks to the beginning of application details requests, before loading private inquiries and assessment data.
- Lock master profile during the new application transaction so submitted fields are read consistently.
- Require explicit HR identity-evidence confirmation for approvals (rejections require reasons, but not proof of ownership).
- Restore legacy age range (18–70) and required middle-name validation for new profiles, consistent with current application form.
- Link applicant-dashboard application codes to authenticated, owner-checked application details.
- No migration alters `applications` or updates historical applicant data.

### Verification needed
- These are code-level fixes. No successful PHPUnit execution or migration dry-run is claimed.
- Run `php artisan route:list`, `php artisan migrate --pretend`, and `php artisan test --filter=Applicant` in a configured staging checkout, followed by full `php artisan test`.
- Verify assessment access for active examinees during the transition, OAuth verified-email claim from Google, staff onboarding, and third-party read-only database contract.
- Do not merge Draft PR #46 without passing security and staging regression tests.

## Full-suite failing tests reconciliation (October 18)
Local report: 25 tests, 3 failures; applicant-only tests had passed 11/11.
- AssessmentRegressionTest: both eligibility tests previously authenticated via guest_email session, which is intentionally no longer trusted. Tests now create a proper applicant profile and ownership link and call actingAs(user); expected 403 for not-taken-in remains.
- ExampleTest: homepage is intentionally a 302 redirect to /applications, so assert that redirect.
- These are regression test fixture/expectation updates; rerun full suite locally before making any pass claims.

## Lifecycle integration-test phase (October 18, 2026)
Added `tests/Feature/ApplicantLifecycleIntegrationTest.php` with six integration checks:
1. Exact normalized email matching auto-links once, never rewriting historical personal information.
2. Existing application ownership cannot be taken over by a subsequent email match.
3. Different-email claim remains pending until HR approves; decision is audited.
4. Claimant cannot approve their own request.
5. New application copies master-profile fields, preserves legacy code and creates a single ownership link; duplicate submission rejected.
6. Updating master profile leaves historical records unchanged.

**Test execution not available through this GitHub connector.** Run locally:
`git pull --ff-only origin feature/applicant-portal-foundation`
`./vendor/bin/phpunit --filter ApplicantLifecycleIntegration --testdox`
`./vendor/bin/phpunit --testdox`

Do not merge before resolving any failures and testing real OAuth callback and assessments in staging.

## Staff Google login hardening (October 18, 2026)
- Existing /auth/google/callback now validates Google subject, verified email, DepEd domain and currently authorized active staff role.
- Staff Google login binds subject into oauth_identities under existing user ID after rejecting cross-account or conflicting-subject associations; applicant login can reuse the same identity.
- Staff callback now uses Socialite's stateful flow and regenerates session after login.
- Added tests/Feature/StaffGoogleCallbackIntegrationTest.php: verified staff login, rejection of unverified Google email, rejection without active staff role, and prevention of subject rebinding.
- OAuth redirect URI for /auth/google/callback must be authorized in Google Cloud settings; existing staff sessions are retained after migration.
- These four tests have NOT yet been run in the user's PHP environment. Prior passing baseline: 31 tests, 96 assertions.
- Run `git pull --ff-only origin feature/applicant-portal-foundation` followed by `./vendor/bin/phpunit --filter StaffGoogleCallbackIntegration --testdox` and `./vendor/bin/phpunit --testdox`; fix any failures before deployment.

## Applicant Google OAuth integration tests (October 18, 2026)
New `tests/Feature/ApplicantGoogleCallbackIntegrationTest.php` adds six mocked OAuth callback tests:
- verified first-time Google login creates applicant user, profile and identity, without a staff role;
- returning Google subject retains the original user;
- verified email auto-links an unclaimed historical application without modifying its personal fields;
- unverified Google email cannot create an applicant;
- an unlinked pre-existing staff user cannot be adopted through applicant login based on email alone;
- an already-linked active staff user can enter applicant portal while preserving staff role.

These tests mock the Google response; they do not simulate a real browser OAuth state and external Google handshake. Previously reported passing baseline was 31 tests and 96 assertions. The new six tests have not yet been executed.
Run `git pull --ff-only origin feature/applicant-portal-foundation`, then `./vendor/bin/phpunit --filter ApplicantGoogleCallbackIntegration --testdox` followed by `./vendor/bin/phpunit --testdox`. Keep PR in draft pending local execution and staging acceptance.

## Assessment access boundary review (October 18, 2026)
- Skills attempt detail controller now authorizes authenticated ownership and eligible assessment access before eagerly loading rubric criteria or submission answers.
- Skills time-status reuses ownedAttempt authorization rather than a separate access path.
- Added test verifying a different authenticated applicant cannot read an already-owned application detail.
- No changes to legacy applications schema.
- Validation pending in user's local environment. Run `./vendor/bin/phpunit --testdox` and review results; do not merge if failing.

## Cross-applicant assessment authorization regression tests
- Added written assessment attempt time-status negative-access test: a separately authenticated applicant without the ownership link receives 403.
- Added skills assessment attempt time-status negative-access test with the same ownership isolation expectation.
- Both tests use existing assessment fixtures and temporary SQLite test data. No changes to the historical applications table.
- Local execution required: `git pull --ff-only origin feature/applicant-portal-foundation` and `./vendor/bin/phpunit --filter AssessmentRegression --testdox` and then `./vendor/bin/phpunit --testdox`.
- Tests do not yet cover actual Google browser OAuth or complete submission flows. PR remains draft.

## Cross-applicant mutation protection tests
- Added two regression tests in AssessmentRegressionTest verifying an authenticated nonowner receives HTTP 403 for written answer autosave, skills inline autosave, and skills attempt submission.
- Assertions also verify unauthorized calls create no answer/submission rows and do not advance attempt status.
- These are additive tests only, without production schema changes. Local PHPUnit execution is still required.
- Run: `git pull --ff-only origin feature/applicant-portal-foundation` then `./vendor/bin/phpunit --filter AssessmentRegression --testdox` and `./vendor/bin/phpunit --testdox`.

## Historical claim conflict regression tests
- Four new lifecycle tests cover HR rejection without ownership linkage, idempotent pending requests, competing claimant approval conflict, and prevention of approvals after final rejection.
- These are additive tests only; the existing application schema and historical records are not changed.
- Run `git pull --ff-only origin feature/applicant-portal-foundation` and `./vendor/bin/phpunit --filter ApplicantLifecycleIntegration --testdox`, followed by the full PHPUnit suite.
- Local verification is outstanding and PR #46 remains draft.

## HR clarification phase
- Added explicit `clarification` status action to manual historical-application claim review, alongside approval and rejection.
- HR must record an actionable reason / evidence request. No ownership link is created merely because clarification is requested.
- Applicant dashboard displays HR clarification instructions and directs applicants to official HR channels; no new evidence-upload feature or insecure email-code self-approval introduced.
- Clarification decision logs `historical_claim_clarification_requested` with reviewer and claim ID.
- Existing service allows subsequent HR approval after independent evidence verification; pending/clarification remain eligible, finalized claims remain immutable.
- Added lifecycle regression test verifying clarification leaves ownership absent until explicit approval.
- Local PHPUnit tests and browser checks remain required; PR #46 is still draft.

## Conflict resolution after HR approval
- When HR approves a verified historical claim, other pending or clarification requests for the same application are closed as rejected in the same database transaction. This avoids permanently unresolvable claims in HR's queue.
- Competing claims receive a neutral notice; the applicant portal never reveals the approved claimant's identity.
- Each conflict closure is audited as `historical_claim_conflict_closed`.
- The competing-claim regression test now checks closed status and audit trail. Local PHPUnit execution remains required.

## First-time applicant deep-link onboarding
- Vacancy application review now creates an empty master profile for an authenticated first-time applicant instead of returning a 404 when no profile exists.
- The applicant is redirected to the master-profile editor and cannot submit until the profile is completed.
- Added ApplicantPortalSecurityTest regression covering this first-time review path and no premature application creation.
- Local PHPUnit execution and real browser review are required; no production deployment has occurred.

## Applicant submission normalization and transaction safeguards
- The submission transaction rechecks master-profile completion under lock.
- Duplicate application matching for the same vacancy uses `LOWER(TRIM(email))` to match legacy records with inconsistent casing or surrounding whitespace.
- Added lifecycle regression tests for historical duplicate email normalization and incomplete-profile submission rejection.
- No changes to the legacy applications table. Run `git pull --ff-only origin feature/applicant-portal-foundation`, then `./vendor/bin/phpunit --filter ApplicantLifecycleIntegration --testdox` and `./vendor/bin/phpunit --testdox`.

## Repeat application review usability
- Authenticated applicants who already have a linked application for a vacancy are redirected to its existing application details rather than seeing a duplicate submission review form.
- Submission endpoint remains authoritative and rejects duplicate attempts inside its transaction.
- Added a lifecycle test asserting the redirect and unchanged application count.
- Tests require local verification; PR #46 remains draft.

## Vacancy closure and access continuity
- Application review deep links redirect an already-linked applicant to the previously submitted application even after the vacancy posting closes or their current master profile becomes incomplete.
- New applicants must still complete their profile and can review only currently open vacancies.
- The submission transaction still denies attempts after the posting deadline using the locked vacancy.
- Two lifecycle regressions cover access to historical submissions after closure and rejection of new late submissions.
- Verify locally with `./vendor/bin/phpunit --filter ApplicantLifecycleIntegration --testdox` and the full PHPUnit suite; draft PR remains unmerged.

## Closed vacancy deep-link behavior
- For a vacancy review URL, existing linked applicants retain access to their application details, including after closure.
- For unlinked applicants, vacancy availability is checked before prompting for a master profile. Closed postings are not presented as actionable application opportunities.
- Added a security regression for first-time users opening a closed vacancy application link.
- Verify with `./vendor/bin/phpunit --filter ApplicantPortalSecurity --testdox` and then the full PHPUnit suite before merge.

## Pending vacancy continuation after profile setup
- New applicant vacancy review keeps only the selected vacancy ID in the server session when profile completion is required.
- Successful profile update consumes that session key and resumes review only if the posting is still open; a closed/deleted vacancy returns the applicant to the dashboard.
- No untrusted redirect URL is accepted, and saving a profile never automatically submits an application.
- Added two feature tests for open and subsequently closed vacancy continuation. Local PHPUnit run is pending.
