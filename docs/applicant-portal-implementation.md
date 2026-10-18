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
