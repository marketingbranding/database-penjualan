# Coordinator + Sales Cutover Checklist

Status values:

- **PASS** — verified by the current audit or existing automated coverage.
- **BLOCKED** — a verified data or scope issue prevents cutover.
- **NEEDS REVIEW** — not safely inferable from current data or requires a
  business-owner decision.

## Current snapshot

| Check | Status | Evidence / next action |
| --- | --- | --- |
| All active Sales exist in OASIS Next | PASS | 59 Team Sales role users are present |
| All active Coordinators exist in OASIS Next | PASS | 16 Coordinator role users are present |
| Roles correctly mapped | NEEDS REVIEW | Team role records exist, but final Team assignment is not normalized |
| Sales → Coordinator mapping verified | BLOCKED | 56 Sales mappings are ambiguous; only 3 have a configured Coordinator candidate |
| Final Team membership verified | BLOCKED | All 9 active Teams are legacy migration containers |
| Lead ownership verified | BLOCKED | 20 Lead Team/Sales PIC/Branch mismatches |
| Lead Team scope verified | BLOCKED | Requires final Team mapping and Lead repair |
| Agenda ownership verified | NEEDS REVIEW | Current snapshot contains 0 Agendas; production/source verification is still required |
| Coordinator dashboard scope verified | BLOCKED | 15 of 16 Coordinators have no usable `coordinator_id` Team |
| Sales dashboard scope verified | BLOCKED | Dashboard code is covered, but 56 Sales have ambiguous final Team scope |
| Quick actions work | PASS | Existing PHPUnit/UI verification covers current flows |
| Lead create works | PASS | Existing ownership and feature tests pass |
| Lead follow-up works | PASS | Existing Lead activity/follow-up tests pass |
| Agenda create/complete works | PASS | Existing Agenda tests pass; production data is absent in this snapshot |
| Coordinator lead assignment works | PASS | Existing ownership tests pass for configured Team cases |
| Coordinator agenda assignment/redirect works | PASS | Existing redirect and policy tests pass |
| No cross-Branch exposure | PASS | No cross-Branch or multi-active-Team memberships detected |
| Legacy CRM final synchronization strategy defined | NEEDS REVIEW | Use the one-time freeze → sync → verify procedure |
| Freeze timestamp defined | NEEDS REVIEW | Business owner must choose timestamp and timezone |
| Final migration run procedure defined | PASS | Documented in `team-normalization-plan.md` |
| Post-migration verification defined | PASS | Audit + full test + operational smoke checks documented |
| Rollback/recovery procedure defined | PASS | Backup, mapping, stop-write, and repair-forward steps documented |

## Readiness decision

### Sales: NOT READY

Sales users have active Team memberships, but the current Teams are legacy
containers and the Coordinator mapping is not final. The 20 Lead mismatches
also mean some migrated records could be shown under an incorrect operational
scope after freeze.

### Coordinators: NOT READY

Only one of 16 Coordinator role users currently has a Team whose
`coordinator_id` points to them. The remaining users cannot safely receive the
intended Coordinator dashboard scope.

## Exact blockers before CRM freeze

1. Confirm the real Coordinator → Sales mapping for every Branch.
2. Create/finalize one Team per Coordinator grouping, without assuming one
   Branch equals one Team.
3. Set `coordinator_id`, project context, memberships, and Team roles.
4. Reconcile the 20 Lead mismatches and re-run the audit.
5. Verify Agenda data from the final source export; the current snapshot has no
   Agenda rows to validate.
6. Define and rehearse the final synchronization, freeze timestamp, verification,
   and recovery procedure.

Do not mark the cutover ready only because PHPUnit or the build passes. The
data-level audit must have zero critical findings first.
