# Assessment Device Sessions (Written and Skills)

## Rule
An applicant may have only one active authenticated browser session **across both Written and Skills assessment attempts**. This is a server-side, database-backed lock; browser cookies and local storage do not determine ownership. An applicant may refresh or open additional tabs in the **same Laravel session**. Attempts to start, view, autosave, upload, record events, or submit an attempt from a different session receive HTTP 409, with a message instructing the applicant to sign out of the original device.

This is a **session** restriction, not reliable identification of unique physical devices: a deliberately duplicated session cookie cannot be distinguished from the original browser. Standard HTTPS, secure cookies and exam-integrity controls remain necessary.

## Recovery and timer policy
To switch devices, the applicant signs out on device A before opening the assessment on device B. A **Sign Out** button appears in the Written and Skills assessment toolbars. Signing out releases only that browser session's device locks, without changing answers, final results or assessment deadlines. The timer **continues** and no new attempt is created.

If device A is unavailable, HR may go to **Assessment Center → Administration → Device Sessions** and release that applicant's device lock(s), entering an auditable reason of at least 20 characters. Releasing only clears the lock, not an attempt's score, submission status, available attempts or expiry. The old browser may subsequently be blocked by the new browser's lock; the candidate should not keep both browsers active.

## Implementation
- `assessment_device_sessions` has a unique index on assessment kind and attempt ID, a user ID, HMAC hash of Laravel session identifier, released_at, timestamps.
- On claim, an SQL transaction locks the **user row** before the attempt row, serializing concurrent start requests even across different assessment kinds.
- Only the same browser session can reclaim its active lock. A different authenticated session for the same applicant with any unreleased attempt lock receives HTTP 409. Ownership checks run before claim on every route.
- The standard Laravel logout and explicit Google-account switching release only locks matching that authenticated browser session.
- Completed/voided attempts do not acquire new locks. No scores, answers or status transitions are modified by the lock itself.
- `resources/views/errors/409.blade.php` displays an understandable conflict message without sensitive applicant data.

## Deployment checklist
1. Back up recruitment and assessment data.
2. Apply the migration with `php artisan migrate --force` **before** deploying code that expects the new table.
3. Ensure all web workers use the same database and session configuration. Make sure HTTPS and secure session cookies are configured in production.
4. Test with synthetic applicants and two isolated browser profiles/devices: first device starts Written, second device cannot start or view Written **or Skills**, autosaves/submit attempts on second device are blocked.
5. Verify first-device logout releases the lock and second-device access resumes **without** resetting the timer or erasing saved answers.
6. Test the HR device-session recovery UI, audit record, and non-HR denial. Also test browser crash and sign-in recovery operationally.
7. Review 409 responses from both page navigation and fetch/autosave clients; check that users receive meaningful messages.

## Limitations
- Closing a tab or losing network connectivity does **not** count as logout; lock remains until explicit sign-out or HR-authorized recovery. This is intentional to prevent an unnoticed second-device takeover.
- No client clock or IP fingerprint is relied on to determine device identity.
- The first device may continue showing an already-loaded exam page; all subsequent protected server requests will enforce the current lock.
