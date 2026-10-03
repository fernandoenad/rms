# Assessment Center load testing

These k6 scenarios exercise the real guest lookup, assessment start, and autosave endpoints. Use them only against a disposable staging environment with synthetic applicants. Do **not** run them against production recruitment data.

## What is covered

- guest email lookup and session creation;
- written-assessment start/resume;
- repeated written answer autosaves;
- skills-test start/resume;
- repeated skills inline-response autosaves;
- concurrent Laravel sessions and CSRF validation;
- application-level rate limits;
- database row locking on attempt saves;
- the same persistence routes used by real applicants.

The scripts intentionally do not submit/finalize attempts. This allows a staging fixture to be reused while concentrating load on the high-volume save paths.

## Prerequisites

1. Apply all migrations, including the high-volume index migration.
2. Run:
   `php artisan assessments:db-diagnostics --explain`
3. Create a staging Written Assessment and/or Skills Test with an open schedule.
4. Create synthetic taken-in applicants for that vacancy. Use at least as many unique applicants as the maximum VU target.
5. Create `loadtests/k6/data/users.json` from the example format:

```json
[
  {"email":"loadtest001@example.test","application_id":10001},
  {"email":"loadtest002@example.test","application_id":10002}
]
```

Never commit a real applicant fixture. The repository only contains `users.example.json`.

## Recommended progression

Do not jump directly to 1,000 VUs. Establish a baseline and increase gradually:

### 100 concurrent applicants

```bash
k6 run \
  -e BASE_URL=https://staging.example.org \
  -e EXAM_ID=123 \
  -e TARGET_VUS=100 \
  -e LOAD_DATA_FILE=./data/users.json \
  loadtests/k6/written-assessment.js
```

### 500 concurrent applicants

```bash
k6 run \
  -e BASE_URL=https://staging.example.org \
  -e EXAM_ID=123 \
  -e TARGET_VUS=500 \
  -e LOAD_DATA_FILE=./data/users.json \
  loadtests/k6/written-assessment.js
```

### 1,000 concurrent applicants

```bash
k6 run \
  -e BASE_URL=https://staging.example.org \
  -e EXAM_ID=123 \
  -e TARGET_VUS=1000 \
  -e LOAD_DATA_FILE=./data/users.json \
  loadtests/k6/written-assessment.js
```

For Skills Test, replace `EXAM_ID` with `SKILL_TEST_ID` and run:

```bash
k6 run \
  -e BASE_URL=https://staging.example.org \
  -e SKILL_TEST_ID=55 \
  -e TARGET_VUS=500 \
  -e LOAD_DATA_FILE=./data/users.json \
  loadtests/k6/skills-test.js
```

## Default acceptance thresholds

Both scripts currently require:

- HTTP failure rate below 1%;
- check success rate above 99%;
- autosave failure rate below 1%;
- autosave P95 below 2 seconds;
- autosave P99 below 5 seconds.

These are staging acceptance thresholds, not promises about a particular hosting plan.

## What to watch on the server

During each run watch:

- CPU and memory;
- PHP-FPM active/idle workers;
- MySQL/MariaDB CPU and active connections;
- slow-query log;
- disk I/O and free disk;
- application error log;
- Assessment Center average/P95 save latency;
- failed jobs;
- timeout-finalization backlog.

AI scoring/generation is deliberately outside the web-request path. For a save-path load test, keep dedicated `assessment-ai` workers at the same count you plan to use in production, but do not generate unnecessary AI traffic unless that is the scenario being tested.

## After each run

Run:

```bash
php artisan assessments:db-diagnostics --explain
```

Review the query plans and the Assessment Center save-latency panel. If a query still shows a large full-table scan under realistic staging data, optimize that query/index before increasing concurrency.

## Important

A successful 1,000-VU test on staging is evidence for that staging/server configuration. If production has fewer PHP-FPM workers, different MySQL settings, less RAM/CPU, or slower storage, repeat the test on infrastructure that closely matches production before relying on the result.
