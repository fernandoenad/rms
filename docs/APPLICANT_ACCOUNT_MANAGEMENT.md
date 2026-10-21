# Applicant Management and read-only test impersonation

## Purpose
An application email is a legacy record field. Ownership is stored separately in `applicant_application_links`, keyed by `application_id` to prevent multiple owners. Changing `applications.email` **does not** reassign an existing ownership link. Never fix a missing applicant dashboard record by directly deleting or reassigning an ownership row.

## HR account diagnostics
Go to **Administration → Applicant Management** (`/admin/applicant-accounts`).
Search by registered account email, applicant name, or exact historical application code. Open the account to view:
- Current application ownership links, with code, position, legacy stored email and link method.
- Up to 100 historical application records whose stored email matches the account email (case- and whitespace-normalized).
- The ownership state of each matching record: **this account**, **unlinked**, or **another account**.
- Whether an existing **verified Google OAuth identity** matches the account's registered email.

Reconciliation is intentionally **add-only** and requires an active Level 1 HR administrator, a reason of at least 20 characters, and a verified Google identity matching the account email. It runs the existing historical email-matching service and records an audit event. It links only unowned matching historical records, will not detach or transfer a conflicting existing ownership link, and is safe to retry without creating duplicate links. Identity conflicts still require a separate HR verification process.

## Read-only test impersonation
**Production is permanently blocked by code**, even if the enable flag is set. To opt in on an isolated development or staging environment with **synthetic applicant data**, set:

```env
APP_ENV=local
RMS_APPLICANT_IMPERSONATION_ENABLED=true
```

Then run `php artisan optimize:clear` to refresh the effective setting.

From Applicant Management, open an applicant account **without any staff role**, provide a required reason and select **Enter Read-only Applicant View**. The authenticated browser temporarily switches to that applicant's account. A prominent yellow warning bar appears on every permitted applicant page with the button **Return to Administrator**.

Allowed GET pages are the applicant dashboard, profile editor (view only), already-owned application details, public landing page and vacancies index/detail. All other GET pages, all non-GET methods (including application submission, exam start/answers/submission, edits, claim actions, logout, Google OAuth redirect and account changes) are blocked with HTTP 403. The sole permitted write is the POST to return to the original administrator.

Session identifiers/tokens regenerate at each transition. Nested impersonation, role-bearing/staff targets, disabled HR admins and non-HR initiators are rejected. Both start and stop are logged in `applicant_audit_logs`. A disabled admin account cannot be restored silently.

To end the session always use **Return to Administrator**. To force a safety stop, disabling the feature prevents further applicant navigation but deliberately still permits the return action.

**Do not enable against a clone containing real applicant data without appropriate organizational approval and access controls.** Testing with synthetic profiles and redacted snapshots is preferred.

## Verification
```bash
php artisan test --filter=ApplicantAccountManagementTest
php artisan test --filter=ApplicantPortalSecurityTest
```

Check authorized HR listing and search, three-record ownership diagnostics, safe reconciliation, repeated reconciliation, blocked impersonation by ordinary staff, blocked writes while impersonating, persistent read-only banner, and restored administrator identity after the stop action.

No new migration is required; the feature uses the existing applicant profile/link/OAuth and applicant audit tables. Before production deployment, leave `RMS_APPLICANT_IMPERSONATION_ENABLED` **unset/false**, and ensure `APP_ENV=production`.
