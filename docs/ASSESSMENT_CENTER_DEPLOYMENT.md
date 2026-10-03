# Assessment Center Upgrade — Deployment Notes

## What this branch adds

- Written exam sets per vacancy/position and schedule
- Taken-in applicant eligibility as the default access rule
- Optional bulk applicant assignment by application code
- Schedule-based applicant access with no enrollment-key prompt
- Server-authoritative timing with confirmed early submission and automatic timeout finalization
- Tab/app-switch audit logging instead of auto-submit
- Immediate answer autosave with persistent refresh-safe question/option order
- Safe option shuffling using option IDs rather than A/B/C/D as answer identity
- Score snapshots at written-exam finalization
- Mobile-first applicant exam UI plus shared mobile responsiveness safeguards
- Skills tests with inline response and file upload
- Skills-test analytic rubrics
- AI-written exam generation using vacancy context plus optional administrator-pasted source material and SOLO abstraction levels
- AI skills-task/rubric generation
- Queued AI skills-test scoring with human-final-score approval
- Equivalent written-assessment groups (Set A/Set B/etc.) with one-set-only applicant locking
- Combined results across equivalent sets, with one written-assessment percentage per applicant

## Deploy

1. Back up the production database.
2. Deploy the branch/merged code.
3. Run:
   php artisan migrate
4. Confirm OPENAI_API_KEY and OPENAI_MODEL are configured before using AI features. The database ai_model_id remains a fallback.
5. Do not use QUEUE_CONNECTION=sync for production AI scoring. This deployment can use QUEUE_CONNECTION=database; make sure the jobs table exists.
6. Start and supervise queue workers using the deployment's process manager.
7. Test one taken-in applicant on a staging vacancy before opening an assessment to a large batch.

## Recommended production settings

For the current RMS deployment:

QUEUE_CONNECTION=database

Run php artisan queue:table only if the jobs migration/table does not already exist, then php artisan migrate. Keep a supervised queue worker running in production.

Redis remains an optional future scaling path; it is not required for the current assessment workflow.

Keep AI jobs outside web-request database transactions. This branch commits skills submissions before dispatching AI evaluation.

## Existing written exam compatibility

The migration creates option records from existing option_a/option_b/option_c/option_d and answer_key values. Existing saved letter-based answers remain readable as legacy answers. New applicant answers use selected_option_id.

Do not roll back the option migration after new option-ID answers have been collected without a data-conversion plan.

## Operational smoke test

- Create a written exam with question and option shuffling enabled.
- Start it as a taken-in applicant during the published schedule; no enrollment key should be requested.
- Select answers and refresh; confirm answers and order remain unchanged.
- Switch tabs; confirm the attempt remains active.
- Let the timer expire; confirm score snapshot and timeout submission.
- Test two applicants behind the same network/IP.
- Create a Skills Test, add a 100-point rubric, publish it, submit inline/DOCX work, and confirm AI produces only a proposed score.
- Finalize the skills score manually in the admin results page.


## Equivalent written-test sets

Use an Assessment Group when several exam sets are alternatives for the same written assessment, for example:

- Set A — Day 1
- Set B — Day 2
- Set C — Day 3
- Set D — Day 4
- Set E — Day 5

An applicant may start only one set in the group. The lock is created when Start succeeds, not when the applicant submits. After a set is started, sibling sets are unavailable to that application.

For controlled daily batches, set each exam to Selected applicants only and bulk-assign application codes to the appropriate set. RMS skips applicants already assigned to another sibling set.

The combined group results page treats the completed attempt percentage from the applicant's one set as the written-assessment score for that group.


## Enrollment-key retirement

Applicants no longer enter an enrollment key. Written-test access is controlled by applicant eligibility, set assignment when applicable, assessment-group locking, exam publication status, and the configured start/end schedule.

The legacy enrollment_key database columns are intentionally retained for backward compatibility with older records and schema constraints, but the value is no longer shown to applicants or administrators and is not used to authorize applicant starts.


## High-volume answer autosave

Written responses still save immediately after an applicant selects an option.

The autosave path is intentionally optimized for high concurrent use:
- applicant/exam/assignment/group authorization is checked through one lightweight query;
- item and option membership are validated in one joined query;
- the answer is persisted with the existing unique (exam_attempt_id, written_exam_id) upsert;
- per-answer audit-event rows are not written because the answer record and updated_at timestamp are already the authoritative persistence record;
- the browser serializes changes per question and coalesces rapid A/B/C/D changes so the newest selection is persisted last;
- final submission is disabled while a response save is still in flight;
- the attempt row is locked only for that applicant during the save, preventing a race between answer persistence and final submission without creating a cross-applicant lock.

Operational audit events such as attempt start, page load/refresh, tab visibility changes, connection changes, timeout, and manual submission remain available.
