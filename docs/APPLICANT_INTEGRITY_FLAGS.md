# RMS applicant integrity flags (draft release)

This feature is under development in draft PR #57. **Do not merge or deploy until all CAR-RQA templates have been updated and regression-tested.**

## Policy and scope
An authorized active Level 1 HR administrator selects a vacancy in Assessment Center → Administration → Applicant Integrity Flags. New threshold settings create a versioned rule set scoped to that vacancy and documented by an HRMPSB authority reference. Recommended initial review criteria are 5+ visibility interruptions, >60s total paired hidden time, >30s average per completed hidden interval, or >120s longest paired hidden interval. Unpaired hidden events trigger a separate technical-data-quality review. Thresholds are **screening heuristics**, not proof of cheating; only review data appropriately governed and explained in the applicable recruitment process.

Click **Run Screening** to evaluate completed, non-voided Written and Skills attempts belonging to the selected vacancy. A matching attempt generates a confidential Pending Review case. Repeat scans under the same rule set do not reopen reviewed cases, and old rule sets remain in historical audit records. Confirmed flags remain effective until explicitly cleared, including across a subsequent change of threshold policy.

HR may also add a manual referral by application code and a documented reason, strictly scoped to the selected vacancy.

## Case review
An authorized HR administrator can confirm or clear a case with a written rationale. Every action is timestamped and associated with the acting user. **Only confirmed** cases are eligible for an asterisk in the **internal** printed CAR-RQA Remarks. Cases cleared by HR do not trigger an asterisk. No score, applicant rank, qualification status, or original Remarks JSON field is modified by flagging.

The public guest CAR-RQA view is deliberately left unchanged. For internal reporting, an asterisk means **For confidential administrative verification only**, not proven cheating. Do not circulate the confidential review queue or exported audit CSV publicly.

## Metric interpretation
- Switch count: number of distinct transitions into hidden state; duplicate `tab_hidden` events while already hidden do not inflate frequency.
- Total hidden seconds: sum of paired `tab_hidden` → `tab_visible` elapsed intervals, based on server event reception timestamps.
- Average hidden seconds: total paired seconds divided by completed intervals. Undefined if no completed pairs.
- Longest hidden interval: maximum of completed paired intervals.
- Unpaired interval: an unmatched trailing hidden event, recorded as incomplete evidence rather than guessing duration.

Browser visibility does not identify which other app or page, if any, was used. Connectivity and event-delivery delay can affect timing.

## Release gates still outstanding
- Integrate confirmed-only remarks formatting across **all 11** internal CAR-RQA templates and their variants. Four are integrated in this draft; seven remain.
- Decide if signed/issued RQA documents need an explicit immutable flag snapshot before future reviews or flag changes.
- Complete GitHub Actions regression and MySQL integration checks on the final head.
- Carry out staff-only acceptance testing with synthetic applicants for pending, confirmed, cleared, manual referral and public-page isolation.
- Validate that employment and eligibility decisions remain governed by the Board, not by the numeric flagging thresholds.

Deployment of the schema creates three new tables: `integrity_rule_sets`, `applicant_integrity_flags`, and `applicant_integrity_flag_actions`. No automatic scan occurs on deployment.
