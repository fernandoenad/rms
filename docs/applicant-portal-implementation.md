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
