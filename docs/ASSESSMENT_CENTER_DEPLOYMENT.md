# Assessment Center Upgrade — Deployment Notes

## What this branch adds

- Written exam sets per vacancy/position and schedule
- Taken-in applicant eligibility as the default access rule
- Optional bulk applicant assignment by application code
- Server-authoritative timing and timeout-only applicant finalization for written exams
- Tab/app-switch audit logging instead of auto-submit
- Immediate answer autosave with persistent refresh-safe question/option order
- Safe option shuffling using option IDs rather than A/B/C/D as answer identity
- Score snapshots at written-exam finalization
- Mobile-first applicant exam UI plus shared mobile responsiveness safeguards
- Skills tests with inline response and file upload
- Skills-test analytic rubrics
- AI-written exam generation using qualification/job context and SOLO abstraction levels
- AI skills-task/rubric generation
- Queued AI skills-test scoring with human-final-score approval

## Deploy

1. Back up the production database.
2. Deploy the branch/merged code.
3. Run:
   php artisan migrate
4. Confirm OPENAI_API_KEY and the existing ai_model_id setting are configured before using AI features.
5. Do not use QUEUE_CONNECTION=sync for production AI scoring. Use Redis where available, or a durable queue backend supported by the deployment.
6. Start and supervise queue workers using the deployment's process manager.
7. Test one taken-in applicant on a staging vacancy before opening an assessment to a large batch.

## Recommended production settings

For higher concurrent use, prefer Redis for queue, cache, and sessions:

QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis

Keep AI jobs outside web-request database transactions. This branch commits skills submissions before dispatching AI evaluation.

## Existing written exam compatibility

The migration creates option records from existing option_a/option_b/option_c/option_d and answer_key values. Existing saved letter-based answers remain readable as legacy answers. New applicant answers use selected_option_id.

Do not roll back the option migration after new option-ID answers have been collected without a data-conversion plan.

## Operational smoke test

- Create a written exam with question and option shuffling enabled.
- Start it as a taken-in applicant.
- Select answers and refresh; confirm answers and order remain unchanged.
- Switch tabs; confirm the attempt remains active.
- Let the timer expire; confirm score snapshot and timeout submission.
- Test two applicants behind the same network/IP.
- Create a Skills Test, add a 100-point rubric, publish it, submit inline/DOCX work, and confirm AI produces only a proposed score.
- Finalize the skills score manually in the admin results page.
