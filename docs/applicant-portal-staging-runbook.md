# RMS Applicant Portal — Staging UAT Execution Runbook

**Scope:** PR #46 only. **Environment:** isolated staging or a disposable local database cloned with sanitized records. **Prohibited:** destructive testing against production, merging before sign-off, changing the legacy `applications` schema.

## Release gate: manual evidence, not just green CI

The read-only UAT worksheet validator is part of PR #46. A passing environment preflight and green automated CI **do not** establish real-browser UAT or actual third-party reader compatibility.

1. Run the scenarios below with **synthetic records in isolated staging**. Copy the worksheet to a private path **outside the Git worktree**. Do not commit real applicant information, screenshots, tokens, secrets, or internal evidence paths.
2. For all HOME, NAV, PROFILE, OAUTH, SWITCH, APP, CLAIM, ASSESS, SESSION, LEGACY, MOBILE, OPS, and ROLLBACK scenarios, record PASS, FAIL, or BLOCKED with sanitized evidence references.
3. Record the **full 40-character checked-out commit SHA**, the same CI-verified SHA, and the exact GitHub Actions run link, after independently confirming all checks passed.
4. Complete the HR and technical reviewer signatures, six acceptance checkboxes, backup reference, and explicit release decision.
5. Run the read-only release gate against that private, completed worksheet:

```bash
git rev-parse HEAD
php artisan rms:applicant-readiness --strict
python3 scripts/applicant_uat_gate.py --worksheet /private/path/applicant-portal-uat-results.md
```

An exit code of 1 means **BLOCKED** due to missing case results, evidence, matching commit IDs, sign-offs, or approval. An exit code of 0 only means the worksheet is complete for human review; it **does not verify** actual browser behavior, evidence authenticity, HR reviewer authority, or CI status independently. Keep Draft PR #46 unmerged and undeployed until the real external reader test, browser UAT, staging rollback, and release-owner approval are complete.

## 1. Freeze and record the tested build

1. Record the feature-branch commit: `git rev-parse HEAD`.
2. Record `php -v`, `php artisan about`, `php artisan migrate:status`; take a verified restorable DB backup before using sample claims.
3. Run `php artisan optimize:clear` and `php artisan route:list`; confirm applicant and HR claim-review routes exist.
4. Record the relevant passing CI URLs **for exactly this commit**; a newer commit needs its own CI result.
5. Prepare synthetic vacancies/applications and a written mapping of applicant and reviewer test identities. Never upload IDs, OAuth tokens, secrets, or applicant PII to an issue.

### Read-only readiness preflight

Before creating synthetic test records, run:

```bash
php artisan optimize:clear
php artisan rms:applicant-readiness
```

This command uses only connection checks, schema introspection, and `SELECT ... LIMIT 0` against the legacy `applications` table. It **does not write data or display applicant records, database credentials, or Google secrets**. It checks that applicant and HR routes exist, Google OAuth credentials are configured, original application columns are present, new portal tables are installed, and the separate HRMIS `stations` table is reachable. It **cannot** determine which redirect URIs are registered in Google Cloud Console or run the actual third-party application reader.

Use `php artisan rms:applicant-readiness --strict` for the staging release gate: any **FAIL** or **WARN** causes a nonzero exit code. If the HRMIS secondary database is intentionally unavailable in a local environment, `--skip-secondary` suppresses its connection attempt, but marks that gate as **WARN** (and therefore cannot pass `--strict`). Do not paste unredacted logs or credentials into GitHub issues. Record the command results and exact commit SHA in the UAT evidence worksheet.

## 2. Configure Google OAuth and test real redirects

In Google Cloud Console, register **exact** callback URLs used by the deployment, separately for staff, applicant, and (when staff linking is tested) staff linking:

- `{APP_URL}/auth/google/callback` (existing staff login)
- `{APP_URL}/auth/applicant/google/callback` (applicant login)
- `{APP_URL}/auth/staff/link-google/callback` (staff Google identity linking)

Substitute the actual public origin for `{APP_URL}`. Google redirect URIs must match scheme, hostname, port, path, and trailing slash exactly. Preserve the existing working staff callback. For local HTTP callback tests use the origin actually served by Laravel (e.g. `http://127.0.0.1:8000`); use HTTPS for deployed staging. In DevTools / Google error details, inspect the actual `redirect_uri` when a mismatch occurs.

**Acceptance:** applicant Google login succeeds with an unrelated verified Google identity; existing authorized staff login still works. Capture sanitized screenshots of successful callback landing pages and record any OAuth error and redirect URI mismatch separately. Confirm state/CSRF protection; do not log OAuth codes or tokens.

## 3. Staff-to-applicant switching

- Sign in as a preauthorized active staff member. Confirm the existing staff dashboard works and note the test-user ID.
- Use the staff account dropdown **Switch to Applicant Portal**. Confirm the applicant dashboard loads under the **same** user ID, with no duplicate RMS user.
- Use **Switch to Staff Portal** to return. Confirm the staff role and authorized screens persist.
- Sign in with an unrelated applicant-only Google account. Confirm staff navigation is absent and `/admin` and `/admin/applicant-claims` deny access.
- Try a disabled or unauthorized staff fixture and confirm staff access is refused. Do not remove valid authorizations or test with production accounts.
- Verify staff login is not linked on public applicant login and the public footer does not advertise Administration.

### New profile form and secondary HRMIS reference data

After pulling the latest branch, back up staging and apply the **additive applicant profile migration** before UAT:

```bash
php artisan migrate:status
php artisan migrate
php artisan optimize:clear
php artisan rms:applicant-readiness --strict
```

The migration adds nullable `date_of_birth` and `district_id` columns **only** to `applicant_profiles`. It does not modify the legacy `applications` schema. Existing profiles without a birthdate will be prompted to supply one when they next edit the profile.

Verify that the master profile loads **municipalities from `mysql_2.towns`**, **gender and civil status from `mysql_2.dropdowns`**, and **optional preferred districts from `mysql_2.offices`**. The form fetches choices via an authenticated, throttled AJAX endpoint and does not substitute made-up options when the reference database is unavailable. The preflight now checks all four secondary reference tables, including `stations`.

- [ ] Test the form layout and section headings on desktop and mobile.
- [ ] Select municipality, gender, civil status, and optionally a district; confirm the chosen values persist.
- [ ] Enter date of birth and confirm displayed age calculates automatically, including a birthday today/yesterday edge case. The age field must be read-only and **not submitted by the browser**.
- [ ] Submit the form through AJAX; verify inline validation errors for missing/invalid fields, saved confirmation and return to the applicant dashboard or pending vacancy review.
- [ ] Confirm the server rejects invented dropdown values or an implausible age, even when a direct HTTP request supplies `age` manually.
- [ ] Disable/interrupt `mysql_2` **only in isolated staging** and confirm dropdown loading fails visibly rather than allowing arbitrary values.
- [ ] Confirm a profile with an older stored birthdate yields an up-to-date age in a new legacy application snapshot, without changing historical application snapshots.

A preferred district is profile reference data only; selecting it **does not assign the application to a station or change assessment routing**.

### Applicant navigation acceptance

From the **Applicant Dashboard**, confirm each outgoing screen provides a visible, keyboard-accessible way back without browser-history dependency:

- [ ] **Edit Master Profile:** top and bottom **Back to Dashboard** links, with **Browse Vacancies** also available.
- [ ] **Browse Vacancies:** the existing Applicant Portal link in the header remains available to authenticated applicants; the removed extra quick-navigation strip does not appear.
- [ ] **Vacancy Details:** the existing **Back to Vacancies** link remains available.
- [ ] **Review Application:** **Back to Vacancy** and **Dashboard** remain available, without accidentally submitting a form.
- [ ] **Application Details:** breadcrumbs lead to the applicant dashboard.
- [ ] **Written/skills assessment pages:** no extra strip overlays the assessment interface; existing submission and timer safeguards continue working.
- [ ] No redundant quick-navigation strip appears at desktop or mobile widths, for authenticated or public visitors.
- [ ] Back links continue using named internal routes; no arbitrary return URL or HTTP Referer is trusted.

### Vacancy discovery and detail-page acceptance

The public Vacancies pages now match the dashboard design while retaining the existing recruitment routes and open-posting rules.

- [ ] As a visitor, open **Vacancies**: see the gradient header, currently open opportunities, **View Details**, **Apply**, and **Applicant Sign In**, without private dashboard buttons.
- [ ] As an authenticated applicant, verify **Back to Dashboard**, **My Applications**, and **My Profile** buttons on the listing; the details page should have **Back to Vacancies**, **Dashboard**, and **My Profile**.
- [ ] Search by position title or recruitment cycle; nonmatching cards disappear, counts update, and clearing the search restores every open vacancy.
- [ ] **View Details** displays the qualifications, salary grade, base pay, office level, and vacancy count. Confirm multiline requirements display safely without interpreting embedded HTML.
- [ ] Clicking **Apply** preserves the existing Google sign-in/verified-applicant profile/review flow.
- [ ] Closed, future, and draft recruitment postings stay hidden or inaccessible under existing vacancy rules.
- [ ] Verify responsive card and action-button layout on narrow/mobile screens. The previously removed shared quick-navigation strip must remain absent.

### Public homepage and account-aware navigation

The RMS root URL (`/`) is now a **public recruitment landing page**, not the old `/applications` redirect. The existing application lookup remains at `/applications`.

- [ ] As a **visitor**, Home shows up to three recently posted **currently open** vacancies, links to View All Vacancies, and a clear **Sign In** header button that leads to Applicant Google sign-in. The footer contains **Staff Login** only. The previously removed links (Previously applied, Open Application Lookup, and CAR-RQAs) remain hidden.
- [ ] Header menu is simple: **Home**, **Vacancies**, **Sign In**. **CAR-RQAs** is hidden from the header; its existing route remains accessible by direct URL. Closed and scheduled vacancies are not advertised. When no vacancy is open, Home displays an appropriate empty state.
- [ ] As an **authenticated applicant**, the Sign In button becomes a **name dropdown** with Dashboard, My Profile, My Applications, and **Sign Out**. Confirm My Applications opens the application's section of the dashboard.
- [ ] As **authorized active staff**, that same dropdown additionally includes **Staff Portal**. Staff Portal must not be visible to inactive or ordinary applicant accounts.
- [ ] Sign Out uses a **POST** form with CSRF protection and returns the interface to guest navigation.
- [ ] Let an applicant session expire and open the **dashboard**, **profile**, **application details**, **written assessment**, and **skills assessment** routes. Each HTML request redirects to **Applicant Sign In**, never `/login` (the staff login). Authenticated staff-link and staff-only routes continue to require the staff login. Expired AJAX requests return HTTP **401** instead of a staff login HTML page.
- [ ] Confirm the **Staff Login** footer link opens the unchanged staff login and does not appear alongside retired utility links.
- [ ] Check keyboard access, focus states, dropdown operation, responsive navbar collapse, and long display-name handling on desktop and mobile.
- [ ] The redundant extra navigation strip that was previously removed must **not** reappear on any page.

## 4. Applicant application journey

- From public Vacancies, choose an open synthetic vacancy, authenticate, and complete the master profile.
- Review and submit; capture application code, linked owner, and immutable submission snapshot in a secure UAT worksheet.
- Attempt duplicate submission; assert no additional `applications` row and no additional ownership link.
- Edit master profile; confirm the earlier `applications` row retains its original values.
- Close the test vacancy; confirm no new applications are permitted but the previous owner's application remains accessible.
- Repeat with another account and confirm it cannot read the first applicant's record.

## 5. HR historical claim review

### Claimant versus original application identity (all claim tabs)

- [ ] In the **Pending / Clarification** view, verify **Application code** and **Position** match the stored legacy application and its vacancy.
- [ ] Compare the **Claimant** panel's profile name and signed-in account email against the **Application** panel's original submitted name and email. Use synthetic records where the two email addresses and names differ to ensure neither source is accidentally substituted for the other.
- [ ] Repeat this visual comparison on Approved, Rejected, and Cancelled history. Approving or cancelling a claim must not rewrite the legacy application name/email or vacancy position.
- [ ] Confirm that a deleted/missing related application or vacancy is shown as unavailable rather than causing a server error, and that only authorized HR reviewers can see the comparison.



Use a **synthetic unowned historical** application with an old email address and an applicant whose verified Google email differs:

1. Applicant submits historical claim with correct application code and historical email. Confirm **Pending** in applicant dashboard.
2. Before approval, applicant cannot open historical application.
3. As active **level-one** administrator, open **Administration → Historical Claims**, **Pending / Clarification**. Confirm claimant name/code/status, and identity evidence is reviewed **independently, outside the claim form**.
4. Submit an approval **without** checking `identity_verified`; confirm validation error and no ownership link.
5. Request clarification for a different test claim; confirm applicant sees notes but still has no access.
6. Reject a separate test claim; confirm documented reason and no ownership link.
7. Approve the verified test claim with identity confirmation and specific notes; confirm a single ownership link, claimant dashboard visibility, original application snapshot unchanged, and foreign-account denial.
8. Inspect **Approved history** and **Rejected history**. Confirm reviewer, timestamp and notes, with no editing/approval controls for finalized claims.
9. Confirm self-review is prohibited, including by direct POST, and that competing outstanding claims close without revealing another claimant's identity.

**Record:** claim request ID, synthetic application code, reviewer, UTC/local decision timestamp, result, sanitized screenshot, event/audit ID. Do not record IDs of genuine applicants in shared CI outputs.

### Approved claim cancellation (new acceptance case CLAIM-07)

- [ ] In **Approved history**, locate a synthetic **HR-approved** claim. Expand **Cancel Approval**, provide a documented reason (at least 15 characters), acknowledge access revocation, and submit. Confirm redirect to **Cancelled history** with original approval notes, cancellation timestamp, actor and reason retained.
- [ ] Verify the approved claimant immediately loses application-detail and assessment access, while the legacy `applications` row, records, scores and original HR approval evidence are **not deleted or rewritten**.
- [ ] Retry cancellation, attempt it without a reason or acknowledgment, and attempt cancellation as a claimant or unauthorized staff. All must fail without changing ownership or producing duplicate cancellation audits.
- [ ] Verify claims resolved by **Google email matching** cannot be cancelled via this HR-approval-only action; it must never delete an unrelated ownership link.
- [ ] If a previous approval was wrong, submit a **new synthetic claim** after revocation and require normal independent HR identity verification again. Existing rejected competing claims are not silently reactivated.

## 6. Assessments and legacy integration

- On staging MySQL, open the *actual* application details page for approved owner. This rendering is **not fully tested by SQLite CI** and must be independently checked.
- Check assignment and access gates for existing written and skills tests (including timeout/resume), manual score release, and existing HR scoring views.
- Run the **existing third-party reader** of the legacy `applications` table against synthetic legacy and newly submitted rows. Confirm unchanged column contract and values. Record the consumer version, result, and any exception.
- Check database migrations for additions only; no edits to the legacy `applications` schema.

### Final AJAX session-recovery acceptance

- [ ] In a disposable staging browser session, expire the applicant session while editing the **master profile** or preparing a **historical claim**. Submit once and verify a visible **Sign in again** action appears, not the staff login page. The form must not auto-resubmit or report success.
- [ ] Repeat during **application review**. A 401/419 must **not** resubmit the application; sign in and review My Applications to confirm whether the original request completed before attempting another submission.
- [ ] During a **written assessment**, expire authentication in isolated staging and trigger answer-save and timer-status requests. Verify the sign-in banner appears, pending answers are not falsely marked saved, and no automatic retry or final submission occurs. Check the server-side official timer/finalization separately.
- [ ] Repeat in the **skills assessment**. Verify authentication failure stops repeated save attempts. Do not claim an unsaved response is accepted. Confirm the original test timing, completion and integrity rules are unchanged.
- [ ] Test unrelated failures (offline, HTTP 403, 409, 422, 429, 500): they must not incorrectly be labeled session expiry. Check desktop, mobile, and keyboard access.
- [ ] Verify the shared JavaScript behavior test and PHP/MySQL/assessment CI checks have passed on the candidate commit. This does not replace interactive staging UAT.

## 7. Evidence and release decision

For each case, log: case ID, environment, commit SHA, tester, date/time/timezone, synthetic user fixture, expected/actual outcome, pass/fail/blocked, evidence reference, severity and issue link.

**Block release** for OAuth failure, staff privilege elevation, cross-account access, unauthorized claim approval, ownership overwriting, legacy details rendering failures, third-party reader breakage, or any outstanding critical/high issue. Require HR sign-off, technical sign-off, verified rollback and backup restoration plan. Keep PR #46 **draft and unmerged** until every release gate is approved.

**Out of scope:** deployment to production, district delegation/RMS2 architecture, changing existing recruitment data, changing the legacy `applications` table.
