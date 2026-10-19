# Legacy OASIS CRM Organization Forensics

## Scope and safety

This document records a read-only forensic review of the production legacy OASIS
CRM performed on 2026-10-19. It contains aggregate and anonymized findings only.
The detailed legacy-to-OASIS Next identity and membership mapping is stored in
the ignored local artifact:

```text
storage/app/audits/legacy-organization-mapping-2026-10-19.json
```

No production business data was changed. No migration, seeder, deployment,
cache clear, service restart, or source-file edit was performed on production.

## Production snapshot

| Metric | Result |
| --- | ---: |
| Active branches | 9 |
| Users | 91 |
| Active users | 89 |
| Coordinator users | 16 |
| Active Sales users | 57 |
| Current valid Coordinator → Sales rows | 56 |
| Active Sales without a current Coordinator | 1 |
| Inactive Sales without a current Coordinator | 2 |

The legacy application was Laravel 13.34.0 on PHP 8.3.35, using MySQL with
MariaDB 11.8.9. The inspected production source was unchanged by the audit.

## Authoritative hierarchy evidence

The operational relationship is the populated `sales_coordinator_sales`
table:

```text
sales_coordinator_sales.coordinator_user_id
    → users.id (role = sales_coordinator)
sales_coordinator_sales.sales_user_id
    → users.id (role = sales)
```

The current active/date-scoped relation contains 56 rows. All 56 rows pass role
and branch validation, cover 56 distinct Sales users, and contain no duplicate
active Coordinator assignment for a Sales user. This is sufficient evidence to
recover the current Coordinator → Sales membership without manually assigning
those 56 Sales users.

The relationship is distributed across the nine branches as follows. Branch
labels are intentionally anonymized.

| Branch | Coordinators with assignments | Assigned Sales | Unassigned active Sales |
| --- | ---: | ---: | ---: |
| Branch A | 2 | 5 | 0 |
| Branch B | 1 | 3 | 0 |
| Branch C | 4 | 10 | 0 |
| Branch D | 2 | 9 | 0 |
| Branch E | 0 | 0 | 1 |
| Branch F | 3 | 14 | 0 |
| Branch G | 2 | 8 | 0 |
| Branch H | 2 | 7 | 0 |
| Branch I | 0 | 0 | 0 |
| **Total** | **16** | **56** | **1** |

## Meaning of `supervisor_user_id`

`users.supervisor_user_id` is not the current Sales hierarchy source:

- all 59 legacy Sales users have a null `supervisor_user_id`;
- no Coordinator points to a Supervisor, Branch Manager, or other operational
  parent through this field;
- the one observed non-operational relationship is a superadmin-to-superadmin
  row;
- the explicit Coordinator/Sales pivot is populated and internally consistent.

Therefore, `supervisor_user_id` must not be used to reconstruct Coordinator
Teams. It may represent a separate administrative relationship, but current
production data does not establish it as the Sales reporting line.

## Identity and role mapping

All 91 legacy users have a deterministic OASIS Next identity match using a
locally computed SHA-256 comparison of email values. Raw email values were not
saved in the report or committed. Numeric user IDs are not stable: only 9
matched users retain the same numeric ID and 82 are remapped.

The existing migration role map remains relevant for review:

| Legacy role | OASIS Next role | Production observation |
| --- | --- | --- |
| `sales_coordinator` | `koordinator_sales` | 16 users |
| `sales` | `sales` | 59 users, 57 active |
| `staff` | `sales` | No current users |
| `admin` / `branch_manager` | `bm` | 8 `admin` users; no `branch_manager` users |
| `manager` / `supervisor` | `spv` | 1 user of each role |

The role map does not provide the Coordinator → Sales membership; the
`sales_coordinator_sales` rows do.

## Teams and organization tables

The legacy production database has a `teams` table, but it is empty and only
contains `id`, `parent_id`, `code`, `name`, `is_active`, and timestamps. No
legacy Team membership pivot or Coordinator field was found, and the inspected
legacy source has no Team model/query path supplying the operational mapping.

The current OASIS Next audit remains a separate baseline: its nine active Teams
are migration containers, not final Coordinator Teams. The legacy `teams`
table should not be treated as evidence against the populated Coordinator/Sales
pivot.

## Lead mismatch review

The OASIS Next baseline reports 20 Lead ownership mismatches. Their displayed
`SRC-SL-*` identifiers did not match current production `sales_leads.external_lead_id`
values, so numeric OASIS Next Lead IDs and displayed identifiers are not safe
cross-system keys for repair.

These 20 Leads remain unresolved. Before any ownership update, obtain the
preserved source migration/export key or another business-approved privacy-safe
fingerprint, then verify the Sales PIC, Branch, Project, and final Team together.
No Lead was repaired during this audit.

## Automation decision

**Decision: mostly automatable, not fully automatable.**

The following can be reconstructed deterministically:

1. identity matching for all 91 users;
2. one final Coordinator grouping for each of the 16 Coordinators;
3. membership for 56 Sales users from current valid pivot rows;
4. branch validation for every recovered Coordinator/Sales row.

Manual or business-owner decisions remain necessary for:

1. the one active Sales user with no current Coordinator assignment;
2. retention or archival of two inactive Sales users without assignments;
3. the 20 unresolved Lead mismatches;
4. final Team names, project context, and cutover timing.

The safe normalization rule is therefore:

```text
one final OASIS Next Team per recovered Coordinator grouping
membership = current valid sales_coordinator_sales rows
unassigned or unresolved records = quarantine for decision, never guess
```

Do not derive Teams from `supervisor_user_id`, alphabetical order, numeric user
IDs, legacy empty Team rows, or same-Branch membership alone.

## Required next steps

1. Obtain an approved decision for the one unassigned active Sales user.
2. Preserve the final source export and its checksum at the agreed freeze time.
3. Dry-run a Team/membership proposal from the ignored mapping artifact.
4. Resolve the 20 Leads using a trusted source key before any write operation.
5. Create/finalize Teams only after business approval and a rollback mapping are
   available.
6. Re-run the OASIS Next organization audit and require zero critical findings.
