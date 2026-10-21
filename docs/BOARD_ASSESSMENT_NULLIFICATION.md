# Board-Authorized Assessment Nullification — HR Operations

## Purpose
An authorized HR administrator may set the **official recruitment-score criteria** for Written and/or Skills assessments to **0.00** following a documented HRMPSB resolution. This is an administrative invalidation of an assessment cycle, **not** a finding that any applicant cheated. Original attempts, rubric evaluations, exam responses and browser telemetry are never deleted or rewritten.

## Before use
1. Confirm the signed Board resolution and effective scope. Identify the vacancy, selected Written Assessment Group and/or selected Skills Test Group. Groups must have valid **distinct** applicant-score criterion mappings.
2. Finalize outstanding in-progress attempts for selected groups; the operation refuses unfinished attempts.
3. Verify official applicant-score mappings on the vacancy's template. Read the preview carefully: it includes the **union** of applicants who started an attempt in either selected group. Every selected criterion becomes 0.00 for every applicant in that union, even if the applicant attempted only one of the two groups.
4. Confirm this is the Board's intended affected population. Do not use a vacancy-wide bulk edit; other recruitment batches are unaffected.
5. Review downstream CAR/RQA eligibility and any published rankings before applying. A score change can move an applicant across existing cutoff thresholds.

## Apply
Assessment Center → Administration → **Board Nullifications**.
Select vacancy and groups → Preview → inspect applicant sample/count/criteria → enter Board memorandum reference, decision date and recorded rationale → type **NULLIFY**.
The operator's decision is reevaluated inside a transaction with an exact applicant-scope fingerprint. If the scope changes, the system refuses the action rather than making unreviewed edits.

The operation:
- Creates a permanently retained batch and per-applicant/criterion original-value snapshot.
- Sets only selected mapped official criteria to 0.00 and recalculates totals from the vacancy template.
- Preserves applicant workflow status and every unrelated criterion.
- Pauses selected assessment groups without erasing attempts or responses.
- Logs the decision and actor in the Assessment Governance audit within the same transaction.
- Protects the official zeros from direct/periodic score synchronization, integrity repair and manual HR/discrepancy edits.

## Audit and restoration
The register shows every batch and its status. Download the batch audit CSV to review the original criterion values and current official values. Do not publicly distribute these HR records.

An authorized HR administrator may reverse a batch by citing a **new Board authorization**, entering a reason and typing **RESTORE**. Restoration happens atomically: if any selected criterion no longer equals its protected zero, the operation stops and restores nothing. The recorded original values (including missing criteria) are reinstated, while all unrelated current scores remain untouched. History, original and superseding references and audit events stay intact. **Groups remain paused**; HR must separately review whether reopening the old assessment is permitted.

## Guardrails and limitations
- The feature does not delete or void attempts. Applicants' official score criteria become zero as authorized; raw exam evidence remains available for proper audit.
- Nullification is not a formal announcement of misconduct.
- Standalone (ungrouped) examinations are not selected by this workflow.
- The original group may remain on an automatic release policy, but active nullification locks prevent its official score from overriding zero. The applicant-facing exam list labels nullified scores rather than showing the historical raw value as current.
- Published CAR/RQA output should be regenerated as needed after zeroing. No automatic external document recall occurs.
- Changes to production applicant records require intentional human authorization; deploying the feature alone does not zero any scores.
- The schema migration `2026_10_21_000001_create_assessment_nullifications.php` must be applied before use.

## Suggested acceptance test
On a **disposable** environment with synthetic applicants, complete Written and Skills attempts, sync legitimate nonzero mapped scores, apply a Board nullification, verify official zeros and unchanged raw attempts, run the recurring score-sync and Score Integrity workflows, attempt a manual score override, download audit CSV, restore with a superseding resolution, confirm group pause persists and test unauthorized staff access. Never use live recruitment applicants for test data.
