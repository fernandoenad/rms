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
7. Configure the Laravel scheduler to run every minute so expired written attempts are finalized proactively:
   * * * * * cd /path/to/rms && php artisan schedule:run >> /dev/null 2>&1
8. Test one taken-in applicant on a staging vacancy before opening an assessment to a large batch.

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


## Assessment governance and equivalence

Assessment Groups now support a configurable number of equivalent sets rather than assuming a fixed five-set structure. When a group is created, RMS can create draft Set A, Set B, Set C, and so on up to the configured count.

A shared blueprint/TOS may define:
- items per set;
- SOLO distribution;
- difficulty distribution;
- competency/construct coverage.

Every set is checked against the shared blueprint before publication. A set also fails readiness when its schedule/duration is incomplete, an active item does not have exactly four options and one correct answer, an AI/revised item is not approved, or selected-applicant mode has no assignments.

Published sets are treated as immutable. Return an unused set to draft before changing its content/settings. After attempts begin, create a new governed set/version rather than silently altering the administered material.

## AI item governance

AI-generated written items enter Pending Review and cannot satisfy publication readiness until a reviewer approves them. Editing an existing item creates a new item version and retains the superseded version for audit history.

For requests above 20 items, AI generation is queued in 10-item batches. Equivalent-set generation receives the shared blueprint and recent sibling-set questions so the model is instructed not to duplicate or closely paraphrase existing items. The item page shows queued/completed batch progress.

## Live operations and timeout finalization

The Assessment Group results page includes:
- attempted, taking-now, submitted, and completion counts;
- per-set monitoring;
- optional 60-second admin auto-refresh;
- queue backlog, failed-job count, recent answer activity, and open incidents;
- in-progress applicants before submission.

The scheduled command `assessments:finalize-expired` finalizes expired attempts without waiting for the applicant to refresh or revisit the page.

## Controlled retakes and incidents

Started attempts are retained for audit integrity and are no longer hard-deleted operationally. An administrator can void an attempt with a required reason and explicitly authorize a retake on another published equivalent set. The original attempt remains marked Voided and the applicant is re-locked to the approved retake set.

Operational incidents can be recorded for connectivity, device, power, proctoring, administrative, or other issues and later marked resolved.

## Score release

Assessment Groups support four score visibility policies:
- Hidden;
- Manual release;
- After all published set schedules close;
- Immediate after submission.

Until the release rule is satisfied, the applicant portal shows Pending official release instead of the raw/percentage score.

## Submission integrity and offline recovery

The final review modal now asks the server for the authoritative saved-answer count rather than trusting only the browser state.

Unsaved latest selections are also stored temporarily in browser local storage and retried when the page reloads or connectivity returns. The server remains authoritative and rejects saves after expiry.

Assessment endpoint rate limits are keyed to the applicant session/attempt rather than public IP, so many applicants behind the same school/NAT connection are not incorrectly throttled together.

## Analytics and official export

The group analytics page provides:
- per-set submission counts, mean score, and standard deviation;
- cross-set comparability warnings when a set mean differs materially from the group mean;
- item difficulty;
- upper/lower-group discrimination;
- distractor response counts.

These are descriptive diagnostics and should be interpreted with adequate sample sizes.

The official CSV export includes application code, applicant, set, start/submission timestamps, attempt status, raw score, total items, percentage, and void reason.


## Skills-test governance parity

Skills Tests now use the same governance principles as the Written Assessment where they are applicable.

### Readiness and immutability

A skills test cannot be published until:
- the task instructions and schedule are valid;
- at least one submission mode is enabled;
- file settings are valid when file upload is enabled;
- the task is approved;
- the active rubric totals exactly 100 points;
- every active rubric criterion is approved;
- selected-applicant mode has at least one assignment.

Published tasks are immutable. Once an applicant has started, the administered task and rubric remain preserved. Future changes should use **Create Revision**, which creates a new draft task version and copies the rubric as a new pending-review version.

### Rubric and human scoring

Human evaluation is criterion-level. Evaluators enter a score for every active rubric criterion; RMS calculates the official final score from those human ratings. AI scoring remains a proposal only and never becomes the official score automatically.

Rubric versions are retained instead of deleting prior criteria. Prior human ratings continue to reference the rubric version that was actually administered.

### Submission version history

File uploads no longer overwrite earlier files. Every upload creates a new submission version while preserving earlier uploaded files for audit history. Evaluators can inspect inline responses and securely download retained file versions from the results page.

Inline responses continue to autosave. The browser temporarily retains an unsaved inline response locally and retries it when connectivity returns.

### Live skills operations

The skills results page now includes:
- attempted, taking-now, submitted, and human-evaluated counts;
- proactive expired-attempt finalization;
- queue / failed-job health indicators;
- open incident counts;
- optional 60-second admin refresh;
- AI proposal status;
- criterion-level human scoring;
- controlled retake actions.

Run the Laravel scheduler every minute. It now drives both:
- `assessments:finalize-expired` for written assessments; and
- `assessments:finalize-expired-skills` for skills tests.

### Skills incidents and controlled retakes

A started skills attempt is retained for audit history. If a legitimate incident requires a retake, the original attempt can be marked **Voided** with a required reason and the applicant can be explicitly assigned to another published, selected-applicant skills task/revision.

Connectivity, device, power, proctoring, administrative, and other incidents can be recorded and resolved from the live results page.

### Skills score release

Skills Tests support:
- Hidden;
- Manual release;
- After the test schedule closes; or
- Immediate visibility once a human final score exists.

The applicant portal never exposes the AI proposed score as the official result.

### Skills analytics and export

The live results page provides rubric-level human score averages and the mean absolute difference between AI-proposed totals and human-final totals where both exist.

The streamed CSV export includes criterion-level human scores and evaluator notes in addition to the overall final score, AI proposal, timestamps, status, and void reason.

### AI file-scoring limitation

The current AI scorer extracts text from DOCX submissions. Other allowed file formats remain fully available for human evaluation, but are not silently treated as machine-readable. If a submission contains no inline response and the uploaded file cannot be text-extracted, AI evaluation is marked **Skipped** rather than failing the queue job.


## Assessment approval and skills response integrity

Written tests and Skills Tests now carry assessment-level creator/approval metadata.

- If the creator is an administrator (role level 1), the assessment is automatically approved at creation.
- Assessments created by non-admin users remain Pending Approval until an administrator approves them.
- Assessment readiness blocks publication while assessment-level approval is pending.
- Equivalent written-set placeholders and new equivalent sets follow the same creator rule.
- Existing assessments are grandfathered as approved by the migration so the governance upgrade does not unexpectedly block assessments already in operational use.

This assessment-level approval is separate from item/task/rubric review. AI-generated written items and AI-generated/revised Skills content still follow their specific review requirements.

For inline Skills Test responses, browser paste and drag/drop text insertion are disabled. Applicants are instructed to type the response directly into the assessment. This is a browser-side integrity control and should be treated as deterrence rather than proof that external assistance was impossible.


## Shared Assessment Center operations upgrade

The Assessment Center now has a unified operations page at `/admin/assessment-center` covering both Written Assessment and Skills Test activity.

It surfaces:
- applicants currently taking each assessment type;
- timeout-finalization backlog;
- pending written-item review and pending skills human evaluation;
- the dedicated assessment AI queue backlog;
- failed jobs;
- scheduler heartbeat;
- sampled autosave average and P95 latency;
- assessment-content exposure health.

### Dedicated AI queue

All written AI generation and Skills AI scoring use the named queue:

`assessment-ai`

Run this separately from normal RMS background work. For example, production can supervise several workers such as:

`php artisan queue:work database --queue=assessment-ai --tries=3 --timeout=600`

Keep at least one normal/default queue worker as well for unrelated RMS jobs. Scale assessment-AI worker count conservatively against provider rate limits, database capacity, and server resources rather than starting hundreds of simultaneous provider calls.

### Pause, extensions, incidents, and archive/freeze

Written groups, standalone Written Tests, and Skills Tests can pause **new starts** without interrupting applicants already in progress. This is the safe emergency behavior because current attempt timers and autosave remain intact.

Authorized monitors may add documented time extensions to an active attempt. Each extension records minutes, reason, approving user, and timestamp.

Attempt timelines include relevant start, visibility/connectivity, upload/submission, extension, and scoring events.

Archive/Freeze is blocked while attempts are in progress. Once archived, assessment mutation actions are rejected and the assessment remains available only as historical/audit material.

### Applicant accommodations

Approved accommodations can be recorded by application code:
- extra time;
- large-text presentation;
- documentation note.

A Written Assessment Group accommodation applies across its equivalent sets. A Skills Test accommodation applies to that specific task.

### Assessment role separation

Assessment Center capabilities are configurable for non-admin users:
- Author;
- Reviewer;
- Monitor;
- Evaluator;
- Release.

Role-level administrators retain all capabilities. To preserve existing installations, non-admin users with no explicit Assessment Center capability rows continue to use their existing access until permissions are intentionally configured.

Administrator-created Written and Skills assessments continue to be automatically assessment-approved, as required. This does not bypass item/task/rubric review requirements for AI-generated or revised content.

### Assessment content bank and exposure tracking

Approved Written items and Skills tasks are added to the Assessment Content Bank. The bank tracks:
- position;
- normalized fingerprint;
- review status;
- usage/exposure count;
- retirement status;
- selected metadata such as SOLO, difficulty, competency, or task version.

Publication increments exposure. Readiness performs same-position similarity checking against recent bank content and blocks highly similar content at the configured implementation threshold.

The bank can retire content from future reuse without deleting historical records.

### Preview and deployment dry run

Written Tests and Skills Tests now have a Preview & Dry Run page. It shows the applicant-facing material together with:
- assessment readiness issues;
- configured queue connection;
- scheduler heartbeat;
- failed-job warnings.

This is a no-attempt preview and does not affect official attempts, locks, analytics, or scores.

### Save-latency monitoring

RMS samples a small fraction of successful autosaves rather than logging every request:
- Written answers: approximately 1 in 50;
- Skills inline saves: approximately 1 in 25.

The Assessment Center displays recent average and P95 save latency. Sampling avoids turning monitoring itself into a high-volume database bottleneck.

### Score history

Skills final-score changes are append-only in the score-change history. Once a final score exists, changing it requires a documented reason. Bulk approval of AI-proposed rubric scores also creates score-history records.

### Recovery snapshots

Immutable JSON configuration snapshots are created at important governance events such as publication, score release, and archive. Administrators can view/download them from Assessment Snapshots.

Snapshots are recovery/audit checkpoints; RMS does not automatically overwrite live assessment data from an old snapshot.

### Telemetry retention

Optional retention settings:

`ASSESSMENT_PERFORMANCE_RETENTION_DAYS=30`

`ASSESSMENT_EVENT_RETENTION_DAYS=365`

`ASSESSMENT_AI_RAW_RETENTION_DAYS=0`

The scheduled `assessments:prune-telemetry` command removes expired performance/event telemetry. A raw-AI retention value of 0 preserves raw responses indefinitely; configure a positive value only after an explicit organizational retention policy is adopted.

Official attempts, final scores, submissions, governance audit records, score-change history, and assessment snapshots are not treated as disposable telemetry by this command.

### Production scheduler

The Laravel scheduler must continue running every minute:

`* * * * * cd /path/to/rms && php artisan schedule:run >> /dev/null 2>&1`

It now supports:
- written timeout finalization;
- skills timeout finalization;
- Assessment Center health heartbeat;
- scheduled telemetry retention.



## Load testing and database optimization

The Assessment Center now includes a staged k6 load-test harness under `loadtests/k6/` and a read-only database diagnostics command.

Before a high-volume recruitment window:

1. Deploy to staging and run all migrations.
2. Verify expected indexes and representative query plans:

   `php artisan assessments:db-diagnostics --explain`

3. Prepare synthetic taken-in applicants for the staging vacancy. Use at least as many unique applicants as the intended k6 VU target.
4. Run the Written and Skills scenarios progressively at approximately 100, 500, then 1,000 concurrent VUs rather than jumping directly to maximum load.
5. Review server CPU/RAM, PHP-FPM saturation, database connections, slow-query log, disk I/O, Assessment Center save-latency metrics, failed jobs, and timeout backlog after every stage.

The high-volume index migration adds targeted indexes for:
- applicant guest lookup and vacancy/application-code lookup;
- application assessment eligibility;
- vacancy/schedule assessment discovery;
- written item publication/review lookup;
- global and per-assessment timeout scans;
- recent written-answer activity;
- assessment-group lock lookup by applicant;
- skills pending-human and timeout scans;
- versioned skills submissions;
- queued skills AI evaluation lookup.

The unified Assessment Center dashboard also consolidates several status counters into aggregate queries rather than issuing separate count queries for every metric.

The k6 scenarios exercise real session, CSRF, start/resume, and autosave routes. They do not bypass applicant access controls and therefore require unique staging applicants.

Default load-test acceptance thresholds are:
- HTTP failure rate below 1%;
- successful checks above 99%;
- autosave failure rate below 1%;
- autosave P95 below 2 seconds;
- autosave P99 below 5 seconds.

See `loadtests/k6/README.md` for commands and fixture format.

Do not treat a successful staging load test as a guarantee for materially different production hardware. The final pre-launch test should use infrastructure that matches production PHP-FPM, database, CPU/RAM, and storage characteristics as closely as practical.


## Applicant assessment score synchronization

Written and Skills assessments can now be mapped to an existing numeric criterion in the vacancy's recruitment assessment template.

The mapping is configured from the assessment create/edit page using **Applicant score criterion**. RMS does not guess or overwrite unrelated criteria.

Score conversion is proportional to the criterion maximum. Example:

- recruitment template criterion: `Written Examination = 20`
- applicant Written score: `85%`
- value written to the applicant assessment record: `17.00`

For Skills Tests, the human-final rubric score is out of 100 and is scaled the same way.

Synchronization rules:
- **Written Assessment Group / Manual**: bulk sync occurs when scores are released.
- **Written Assessment Group / Immediate**: each submitted scored attempt syncs immediately.
- **Written Assessment Group / After close**: the scheduler syncs after all published set schedules close.
- **Written Assessment Group / Hidden**: no applicant-score synchronization occurs.
- **Standalone Written Test**: when explicitly mapped, a submitted scored attempt syncs immediately because standalone tests do not have a group score-release policy.
- **Skills / Manual**: bulk sync occurs when scores are released.
- **Skills / Immediate**: a human-final score syncs immediately after finalization/approval.
- **Skills / After close**: released final scores are synchronized by the scheduler once the schedule closes; later human finalizations are synchronized as they occur.
- **Skills / Hidden**: no applicant-score synchronization occurs.

The synchronizer changes only the selected mapped criterion, preserves all other applicant assessment criteria, recalculates the applicant's assessment total from the recruitment template, and preserves the existing assessment workflow/status.

The scheduler command is:

`php artisan assessments:sync-official-scores`

It is scheduled every minute and also backfills mapped released assessments that have not yet been synchronized.
