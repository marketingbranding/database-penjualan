# OASIS Next Lead Team Reassignment

## Scope and rule

This operation changes only `leads.team_id`. It does not rebuild Leads and does
not change `sales_pic_id`, `created_by`, status, activities, follow-up history,
consumer data, ownership credit, or timestamps.

The authoritative rule is:

```text
Lead.team_id = final active Team of Lead.sales_pic_id
```

`created_by` is intentionally ignored. A Coordinator may create a Lead for a
Sales user, but the Lead belongs to the Sales user's operational Team.

## Command contract

Dry-run is the default:

```bash
php artisan oasis:reassign-lead-teams
```

An explicit mapping path and report path can be supplied:

```bash
php artisan oasis:reassign-lead-teams \
  --mapping=storage/app/audits/legacy-organization-mapping-2026-10-19.json \
  --report=storage/app/audits/lead-team-reassignment-2026-10-19.json
```

Apply changes only with:

```bash
php artisan oasis:reassign-lead-teams \
  --mapping=storage/app/audits/legacy-organization-mapping-2026-10-19.json \
  --apply
```

Production apply additionally requires:

```text
--confirm=REASSIGN-OASIS-NEXT-LEAD-TEAMS
```

The command requires the versioned organization mapping so unassigned and
inactive Sales remain explicit exceptions. Reports contain local numeric IDs
and rollback evidence only and must remain under ignored storage.

## Classification

Every Lead is classified exactly once:

- `EXISTING_CONSISTENT`: the current Team is the active final Team of the
  Sales PIC;
- `SAFE_REASSIGN`: the Sales PIC has exactly one active normalized final Team,
  Sales and Team Branches agree, Lead Branch agrees, and the old Team is empty
  or a legacy `Migrasi Tim ...` container;
- `NEEDS_REVIEW`: any missing, inactive, ambiguous, cross-Branch, conflicting,
  or otherwise incomplete relationship;
- `EXCLUDED`: reserved for a documented out-of-scope Lead type. The current
  implementation has no excluded Leads.

Final Teams are active `COORD-B...` Teams with a Coordinator pointer,
Coordinator membership and role, and Sales membership plus the team-scoped
`sales` role. Legacy Teams are never used as the target.

The one unassigned active Sales and two inactive Sales are never assigned by
Branch, creator, old Team, or name. Leads for those users are classified as
`unassigned_active_sales` or `inactive_sales` when present.

## Current local result

The safe local database contained 4,051 Leads:

| Classification / reason | Count |
| --- | ---: |
| `EXISTING_CONSISTENT` before apply | 0 |
| `SAFE_REASSIGN` | 4,015 |
| `NEEDS_REVIEW` | 36 |
| `lead_branch_team_mismatch` | 20 |
| `sales_pic_has_no_final_team` | 16 |
| Unassigned active Sales Leads | 0 |
| Inactive Sales Leads | 0 |
| `EXCLUDED` | 0 |

The extra 16 beyond the original 20 mismatches are Leads whose `sales_pic_id`
points to a Coordinator without a valid final Sales Team. They cannot be
reassigned without changing the Lead's Sales PIC or guessing a Team. The
original 20 branch/Team mismatches remain untouched.

## Apply and rollback safety

Apply runs inside one database transaction and updates only `team_id` through a
guarded row update. A concurrent change or write failure rolls back every Lead
update. The ignored report records each changed Lead's ID, old Team, new Team,
Sales PIC, Branch, and rule. This is rollback evidence; no automatic rollback
command is provided.

The first safe local apply changed 4,015 Leads. A second dry-run and apply
reported 4,015 `EXISTING_CONSISTENT`, 0 `SAFE_REASSIGN`, 36 `NEEDS_REVIEW`, and
0 changed rows.

No legacy Team was deleted or deactivated. The post-apply organization audit
reports 36 remaining critical Lead mismatches, exactly matching the explicit
review set.

## Dashboard and creation verification

Local feature coverage verifies that Sales and Coordinators see Leads through
the reassigned Team scope without seeing another Coordinator Team's Leads.
Existing Lead creation coverage continues to verify:

- Sales-created Leads use the Sales PIC's Team;
- Coordinator-created Leads preserve `created_by` as the Coordinator and use
  the selected Sales PIC's Team;
- Lead PIC changes continue through `LeadOwnershipService`, preserving Team
  scope and rejecting cross-Team assignments.

## Remaining blockers

Lead production apply remains blocked until the 36 review Leads receive a
business-approved disposition, the unassigned active Sales and inactive Sales
retention decisions are complete, and the final Team normalization has been
approved for production. UAT, freeze timing, and the separate OASIS CRM freeze
decision remain pending.
