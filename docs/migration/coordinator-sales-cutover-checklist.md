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
| Active Sales are present in OASIS Next | NEEDS REVIEW | 59 Sales role users are present; 57 are active |
| All active Coordinators exist in OASIS Next | PASS | 16 Coordinator role users are present |
| Roles correctly mapped | PASS (LOCAL) | Final Team role records were provisioned locally; production apply not executed |
| Sales → Coordinator mapping recovered | PASS | 56 current valid legacy pivot rows cover 56 Sales users |
| Unassigned active Sales resolved | NEEDS REVIEW | One active Sales user has no current Coordinator assignment |
| Final Team membership verified | PASS (LOCAL) | Safe local apply created/reused 16 final Coordinator Teams; production apply not executed |
| Lead ownership verified | BLOCKED | 36 review Leads remain: 20 Branch mismatches and 16 Sales PICs without a final Team |
| Lead Team scope verified | PASS (LOCAL PARTIAL) | 4,015 Leads reassigned safely; 36 unresolved records remain untouched |
| Agenda ownership verified | NEEDS REVIEW | Current snapshot contains 0 Agendas; production/source verification is still required |
| Coordinator dashboard scope verified | PASS (LOCAL) | Post-normalization local audit reports 16 confirmed Coordinator mappings |
| Sales dashboard scope verified | BLOCKED | Local smoke test passes for reassigned Leads; one active and two inactive Sales exceptions remain |
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

Sales users have final local Team memberships, but one active Sales assignment
is unresolved and two inactive Sales records need disposition. Thirty-six Leads
remain outside deterministic reassignment, so production cutover is blocked.

### Coordinators: NOT READY

The legacy source provides valid membership rows for all 16 Coordinators and
the safe local apply confirms their final Team scope. Production Team apply and
the remaining Lead review set still require approval.

## Exact blockers before CRM freeze

1. Confirm the one active Sales user without a current Coordinator assignment.
2. Review and approve the Team normalization report before production apply.
3. Resolve the one unassigned active Sales and the two inactive Sales decisions.
4. Reconcile or disposition the 36 Lead review records and re-run the audit.
5. Verify Agenda data from the final source export; the current snapshot has no
   Agenda rows to validate.
6. Define and rehearse the final synchronization, freeze timestamp, verification,
   and recovery procedure.

Do not mark the cutover ready only because PHPUnit or the build passes. The
data-level audit must have zero critical findings first.
