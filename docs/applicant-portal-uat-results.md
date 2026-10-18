# RMS Applicant Portal — Phase 6 UAT Results Sheet

**Project:** RMS Applicant Portal, Draft PR #46

**Test environment:** ____________________

**Branch / exact commit SHA:** ____________________

**Test date and timezone:** ____________________

**Tester:** ____________________

**Database backup reference (staging only):** ____________________

**Instructions:** Complete with synthetic records only. Enter PASS, FAIL, or BLOCKED; attach sanitized screenshot/log evidence, and identify a defect for every failure. Do not record passwords, OAuth authorization codes, tokens, real applicants' personal details, or sensitive documents. Automated CI results do not count as manual UAT evidence.

| ID | Scenario | Expected result | Status | Evidence / defect |
| --- | --- | --- | --- | --- |
| OAUTH-01 | Authorized RMS staff signs in with existing Google callback | Staff identity and role preserved | NOT RUN | |
| OAUTH-02 | Verified ordinary Google account signs in through applicant callback | Applicant profile created without staff privileges | NOT RUN | |
| SWITCH-01 | Active staff switches from AdminLTE to applicant dashboard and back | Same RMS user ID, no duplicate user, no role elevation | NOT RUN | |
| SWITCH-02 | Applicant attempts direct staff and HR claim-review URLs | Access denied; no sensitive information disclosed | NOT RUN | |
| APP-01 | Applicant selects an open vacancy before sign-in, completes profile, reviews and submits | Exactly one legacy application row and one ownership link | NOT RUN | |
| APP-02 | Repeat submit, closed vacancy, and edit master profile after submission | No duplicate, closed vacancy blocks new submission, historical snapshot unchanged | NOT RUN | |
| CLAIM-01 | Different-email historical claim before HR review | Pending and application inaccessible | NOT RUN | |
| CLAIM-02 | HR tries approving without independent identity verification | Validation rejection, no link or approval audit entry | NOT RUN | |
| CLAIM-03 | HR requests clarification or rejects a different claim | Recorded reason and audit; no ownership granted | NOT RUN | |
| CLAIM-04 | Level-one administrator verifies and approves claim | One link, audit entry, approved applicant gains access | NOT RUN | |
| CLAIM-05 | Unrelated applicant opens approved application | Access denied | NOT RUN | |
| CLAIM-06 | Self-review, conflicting claims and finalized claims | No unauthorized approval, takeover or second decision | NOT RUN | |
| ASSESS-01 | Approved applicant opens legacy application detail and assigned assessments | MySQL-backed views render and rightful owner can proceed | NOT RUN | |
| ASSESS-02 | Foreign applicant attempts written/skills assessment access and writes | Denied; no changes to foreign attempts or scores | NOT RUN | |
| LEGACY-01 | Existing third-party consumer reads old and newly created `applications` rows | Existing field contract unchanged, no consumer errors | NOT RUN | |
| MOBILE-01 | Applicant login/profile/dashboard and sign-out on mobile | Functional navigation and no layout obstruction | NOT RUN | |
| OPS-01 | Review application, session and OAuth logs after test journeys | No unexplained error and no secret/token leakage | NOT RUN | |
| ROLLBACK-01 | Validate staging backup recovery procedure | Documented, restorable backup and rollback owner | NOT RUN | |

## Defect record

For each failure, record: **case ID; severity (critical/high/medium/low); reproduction steps; expected result; actual result; sanitized evidence reference; assigned owner; fix commit; retest result.**

## Acceptance decision

- [ ] Every critical case above is marked PASS; no unresolved critical/high defects.
- [ ] Exact candidate commit has green automated CI results.
- [ ] Authorized HR reviewer signs off historical-claim approval and privacy checks.
- [ ] Technical owner signs off legacy MySQL details and third-party reader compatibility.
- [ ] Staging database backup and rollback procedure are verified.
- [ ] Release decision recorded with date, approvers, exceptions, and evidence locations.

**HR reviewer / date:** ____________________

**Technical reviewer / date:** ____________________

**Decision:** NOT APPROVED / APPROVED FOR NEXT RELEASE STAGE

**Notes and exceptions:** ____________________

Keep PR #46 draft and unmerged until approval. This worksheet is a template, **not** evidence that UAT has occurred.
