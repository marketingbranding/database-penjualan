# Coordinator + Sales Cutover Checklist

Status values:

- **PASS** — verified by the current audit or existing automated coverage.
- **BLOCKED** — a verified data or scope issue prevents cutover.
- **NEEDS REVIEW** — not safely inferable from current data or requires a
  business-owner decision.

## Current snapshot

| Check | Status | Evidence / next action |
| --- | --- | --- |
| All legacy Sales identities are matched | PASS | 91/91 legacy users have a deterministic privacy-safe identity match |
| Active Sales are present in OASIS Next | NEEDS REVIEW | Fresh production source audit: 59 Sales role users; 57 active; one active Sales (ID 76) remains unassigned |
| All active Coordinators exist in OASIS Next | PASS | 16 Coordinator role users are present |
| Roles correctly mapped | PASS (LOCAL) | Final Team role records were provisioned locally; production apply not executed |
| Sales → Coordinator mapping recovered | PASS | 56 current valid legacy pivot rows cover 56 Sales users |
| Unassigned active Sales resolved | NEEDS REVIEW | Fresh production source confirms active Sales ID 76 has no current Coordinator assignment and owns 0 active Leads |
| Final Team membership verified | PASS (LOCAL) | Safe local apply created/reused 16 final Coordinator Teams; production apply not executed |
| Lead ownership verified | BLOCKED | 36 review Leads remain: 20 Branch mismatches and 16 Sales PICs without a final Team |
| Lead Team scope verified | PASS (LOCAL PARTIAL) | 4,015 Leads reassigned safely; 36 unresolved records remain untouched |
| Agenda ownership verified | NEEDS REVIEW | Fresh production source contains 8,069 Buku Saku Sales Agendas; target-side reconciliation is still required |
| Coordinator dashboard scope verified | PASS (LOCAL) | Post-normalization local audit reports 16 confirmed Coordinator mappings |
| Sales dashboard scope verified | BLOCKED | Fresh source has 20 branch-mismatch Leads and 16 Leads assigned to active Coordinator ID 59 without a deterministic final Team; anonymized Sales IDs 94/95 own 0 active Leads |
| Quick actions work | PASS | Existing PHPUnit/UI verification covers current flows |
| Lead create works | PASS | Existing ownership and feature tests pass |
| Lead follow-up works | PASS | Existing Lead activity/follow-up tests pass |
| Agenda create/complete works | PASS | Existing Agenda tests pass; production data is absent in this snapshot |
| Coordinator lead assignment works | PASS | Existing ownership tests pass for configured Team cases |
| Coordinator agenda assignment/redirect works | PASS | Existing redirect and policy tests pass |
| No cross-Branch exposure | PASS | No cross-Branch or multi-active-Team memberships detected |
| Legacy CRM final synchronization strategy defined | PASS (REHEARSED LOCALLY) | Delta importer, reconciliation, and repeat-run procedure are documented in `crm-delta-sync.md` and `cutover-runbook.md` |
| Freeze timestamp defined | NEEDS REVIEW | Business owner must choose timestamp and timezone; the command now requires both `--since` and `--freeze-at` in delta mode |
| Final migration run procedure defined | PASS (REHEARSED LOCALLY) | Documented in `cutover-runbook.md`; production freeze/apply remains unauthorized |
| Post-migration verification defined | PASS | Audit + full test + operational smoke checks documented |
| Rollback/recovery procedure defined | PASS | Backup, mapping, stop-write, and repair-forward steps documented |

## Readiness decision

### Sales: NOT READY

The fresh production source has 56 current valid Coordinator → Sales rows for 57
active Sales users. Active Sales ID 76 remains unassigned, while IDs 94/95 are
anonymized and own no active Leads. Thirty-six current, non-deleted Leads remain
outside deterministic final-Team resolution (20 branch mismatches and 16 Leads
whose PIC is active Coordinator ID 59), so production cutover is blocked.

### Coordinators: NOT READY

The fresh source provides valid membership rows for all 16 Coordinators and the
56-row effective mapping matches the previous forensic mapping by Branch code,
Coordinator ID, and Sales ID. Production Team apply and the remaining Lead
review set still require approval.

## Exact blockers before CRM freeze

1. Confirm the disposition of active Sales ID 76 without a current Coordinator assignment.
2. Review and approve the Team normalization report before production apply.
3. Resolve the unassigned active Sales decision and document the anonymized Sales IDs 94/95 disposition.
4. Reconcile or disposition the 36 Lead review records and re-run the audit.
5. Reconcile the 8,069 source Agendas against the OASIS Next target.
6. Define and rehearse the final synchronization, freeze timestamp, verification,
   and recovery procedure.

Do not mark the cutover ready only because PHPUnit or the build passes. The
data-level audit must have zero critical findings first.

## Current milestone decision

### GO

- OASIS Next delta synchronization is deterministic, dry-run by default, and
  source/target guarded.
- Local delta rehearsal and repeat-run idempotency pass.
- Legacy CRM read-only support is implemented in source control with
  `OASIS_READ_ONLY=false` as the default.

### REVIEW

- The production application account is not read-only: `SHOW GRANTS` indicates
  `ALL PRIVILEGES`. Obtain a separate SELECT-only credential for migration/audit.
- Previous 36 target-side Lead review records cannot be matched one-to-one to
  current source rows because the local snapshot uses `SRC-SL-*` identifiers;
  the fresh source anomaly categories still total 36 active rows.

### BLOCKER

- The live source is now identified and verified as the logical source used by
  the previous audit: the application path/repository, schema, Branch codes,
  user IDs, and 56 effective pivot mappings agree.
- The OASIS Next production target and a trustworthy previous production sync
  boundary are not available from this legacy-server session, so the real delta
  dry-run has not been attempted.
- Production Team normalization, Lead reassignment, target reconciliation, and
  fresh snapshot rehearsal remain pending.
- Production `OASIS_READ_ONLY` has not been enabled, as required for this
  preparation milestone.
