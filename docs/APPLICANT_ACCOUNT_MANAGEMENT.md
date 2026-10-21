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

## Production Support Mode — separate, restricted gate

Production use requires a separate explicit organizational authorization. This is a **support-only, read-only** workflow, not a way to act on behalf of an applicant. An administrator can inspect only the applicant dashboard, read-only profile page, already-owned application details and public vacancy pages. All writes and assessment routes are blocked in central middleware. Test against synthetic data in staging first.

The system remains disabled by default in production. Configure only with an approved list of *existing active Level 1 HR administrator user IDs*:

```env
APP_ENV=production
APP_DEBUG=false
RMS_APPLICANT_IMPERSONATION_ENABLED=false
RMS_SUPPORT_IMPERSONATION_ENABLED=true
RMS_SUPPORT_IMPERSONATION_ADMIN_IDS=123
```

Replace `123` with a genuine approved administrator *user ID*, not the role ID. Keep the allowed list as small as possible. Set `RMS_SUPPORT_IMPERSONATION_ENABLED=false` for an emergency kill switch, then `php artisan config:cache`; any active support session returns to its origin administrator on the next HTTP request. The kill switch is deployment-wide; it is not a browser toggle.

Production support requires the administrator to already have a verified Google identity linked to their RMS staff account. If absent, use the existing authorized **Link Staff Google Identity** flow first. Starting a session launches a **new Google OAuth identity challenge**, with account selection and a zero-age request. RMS verifies the returning Google subject, verified email and bound staff identity *without creating or updating identity links*. Google may maintain its own sign-in session, so this step-up is **not a guarantee of a fresh password or MFA challenge**; enforce additional IdP session and MFA requirements at the organization level when necessary.

A successful challenge opens a distinct applicant session with a server-side 10-minute hard expiry. The banner identifies the support mode and expiry. Session IDs are regenerated on both transitions; the start and stop audit records identify the administrator, target profile/user, reason, session correlation ID and network IP. The stop action works even after the kill switch is activated. Expiry or revocation restores the authorized administrator, or signs out if the administrator was disabled.

Do not enable production support until manual review confirms the appropriate legal/policy basis for privileged PII viewing, access-log retention, and any required user notification. A server-side environment variable alone cannot substitute for those operational approvals. Also confirm HTTPS-only cookies and secure sessions in production.

### Production acceptance checks

- Unlisted HR administrator: **403**.
- Listed HR user without a verified, pre-linked matching Google subject: **403**.
- Google OAuth callback with wrong Google subject, unverified email or expired pending challenge: **403, no applicant login**.
- Applicant account with a staff role: **403**.
- Allowed pages: dashboard, existing owned-application details, profile **view only**, and vacancies.
- Blocked pages/actions: assessments, enrollment and application submission, edits, saved answers, profile update, claims, logout and staff admin pages.
- At 10 minutes and after an emergency kill switch: automatic return to admin and documented audit event.
- Browser view caches disabled during support viewing; never include protected applicant details in URLs or log metadata.

Enable this feature in production **only after separately approving and checking the actual account IDs**. Normal local test impersonation remains controlled by its original `RMS_APPLICANT_IMPERSONATION_ENABLED` flag.
