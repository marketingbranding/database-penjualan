# OASIS Next Team Normalization Implementation

## Command contract

`oasis:normalize-teams` is a dry-run by default:

```bash
php artisan oasis:normalize-teams \
  --mapping=storage/app/audits/legacy-organization-mapping-2026-10-19.json
```

Apply mode requires an explicit flag:

```bash
php artisan oasis:normalize-teams \
  --mapping=storage/app/audits/legacy-organization-mapping-2026-10-19.json \
  --apply
```

In a production application environment, apply mode also requires:

```text
--confirm=NORMALIZE-OASIS-NEXT-TEAMS
```

The command writes an ignored JSON report to
`storage/app/audits/team-normalization-YYYY-MM-DD.json`. Use `--report` to
choose another local report path.

## Authoritative input

The mapping must be format version `1` and must contain:

- deterministic Branch codes;
- one `next_user_id` for every Coordinator;
- Sales entries nested under their authoritative Coordinator;
- `sales_accounting` categories that reconcile exactly to every OASIS Next
  Sales-role user.

The command rejects missing users, missing expected Team roles, branch
mismatches, duplicate Coordinator/Sales assignments, duplicate final Team
codes, and conflicting active non-legacy memberships before apply mode starts.

`supervisor_user_id` and `reports_to_id` are never used to construct the
mapping. `project_id` and `supervisor_id` remain null for newly created final
Teams because this milestone models a Team as a Coordinator + Sales group,
not as a Project.

## Sales accounting

The normalization report must reconcile every Sales-role user into exactly one
category:

- `MAPPED_ACTIVE` — authoritative pivot membership, moved to a final Team;
- `UNASSIGNED_ACTIVE` — preserved and reported as `NEEDS_MANUAL_MAPPING`;
- `INACTIVE` — preserved and untouched;
- `ANONYMIZED`, `LEGACY_ONLY`, `DUPLICATE_DATA_ISSUE`, or `OTHER` — blocking
  unresolved categories unless explicitly resolved in a replacement mapping.

The current production-derived report accounts for 59 Sales users as 56 mapped
active, 1 unassigned active, and 2 inactive.

## Apply behavior

For every mapped Coordinator, the service creates or reuses one stable final
Team using a technical code of the form:

```text
COORD-B{branch_id}-U{coordinator_id}
```

The Team is active, branch-scoped, and Coordinator-owned. The Coordinator and
mapped Sales users receive final Team membership and the corresponding
team-scoped role. Confirmed users are removed from legacy `Migrasi Tim ...`
memberships and legacy Team role rows. Unassigned and inactive Sales users are
not moved or guessed.

Legacy Teams are retained and remain active during the first apply. Their stale
Coordinator pointer is cleared only when it points to a Coordinator safely
moved to a final Team; the previous state is recorded in the ignored rollback
section. No legacy Team is deleted.

`--deactivate-legacy --apply` is a separate guarded stage. It refuses to run
while active Sales, Leads, Agendas, or legacy memberships remain unresolved.

## Operational records

The command audits Leads, Agendas, and active Sales Cases but does not modify
any of them. Lead results are classified as existing consistent, safe candidate
for a separate deterministic reconciliation, or mismatch needing review. The
20 previously identified Lead mismatches remain untouched.

The command does not change `reports_to_id`, Lead ownership, Agenda ownership,
Sales Case ownership, or Project relationships.

## Idempotency and rollback

Apply mode runs inside a database transaction. Repeating the same apply reuses
the stable final Team codes, uses existing pivot rows and roles, and does not
create duplicates. If an apply operation fails, the transaction rolls back.

The ignored report records created Team IDs, previous legacy memberships,
previous Team roles, previous defaults, and legacy Team state required for a
reviewed rollback decision. No automatic destructive rollback command is
provided by this milestone.

## Current readiness

The local safe apply verified:

- 16 final Teams;
- 16 confirmed Coordinator mappings;
- 56 confirmed Sales mappings;
- 9 retained active legacy Teams;
- no `reports_to_id` changes;
- 0 Agendas and 0 active Sales Cases in the local snapshot.

This is **Team Normalization Ready for review**, not cutover-ready. The one
unassigned active Sales user, two inactive Sales records, Lead mismatches, and
UAT remain blockers before freezing OASIS CRM.
