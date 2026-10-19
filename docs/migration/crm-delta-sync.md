# OASIS CRM Delta Synchronization

## Scope and safety

`migration:import-oasis` remains dry-run by default. Delta mode requires both a
previous sync timestamp and an explicit source freeze timestamp:

```bash
php artisan migration:import-oasis \
  --source=legacy_oasis \
  --delta \
  --since="2026-10-19 10:00:00" \
  --freeze-at="2026-10-19 18:00:00" \
  --timezone=Asia/Jakarta \
  --report=storage/app/audits/crm-delta-dry-run.json
```

The timestamps are interpreted in the supplied timezone and are recorded in the
JSON report. Rows newer than the freeze timestamp are excluded and reported.
The source database is never written by this command.

The legacy connection is configured only through environment variables. Keep it
separate from `DB_*`, use a SELECT-only database account where possible, and do
not make it the default connection:

```dotenv
LEGACY_DB_CONNECTION=mysql
LEGACY_DB_HOST=legacy-db-host
LEGACY_DB_PORT=3306
LEGACY_DB_DATABASE=legacy-db-name
LEGACY_DB_USERNAME=legacy-read-only-user
LEGACY_DB_PASSWORD=secret-supplied-outside-git
```

The command rejects `--source` when it equals the target connection and checks
the required legacy tables before reading rows. It also rejects an identical
source/target database identity outside the test environment. Database/schema
identity, Branch codes, migration maps, user mappings, and Lead IDs must still
be reconciled by the operator before a production delta is trusted.

## Import behavior matrix

| Entity | Stable source key | Target key | New row | Existing row | Source change propagation | OASIS Next protection |
| --- | --- | --- | --- | --- | --- | --- |
| Branch | `branches.id` | `legacy_migration_maps` → `branches.id` | Insert | Map reuse | Name, code, active state | No final Team is touched |
| Project | `lead_master.id` | `legacy_migration_maps` → `projects.id` | Insert | Map reuse | Name, branch, active state | No Lead Team inference |
| Legacy Team | `branches.id` | `LEGACY-{BRANCH_CODE}` | Insert | Map reuse | Legacy container fields only | Final Teams are never selected as legacy targets |
| User | `users.id` (email fallback on first import) | `legacy_migration_maps` → `users.id` | Insert | Map reuse | Identity, password, branch | `reports_to_id`, team memberships, and roles are not rewritten in delta mode |
| Lead | `sales_leads.id` / `CRM-{id_lead}` | `legacy_migration_maps` → `leads.id` | Insert once | Map reuse | CRM fields, status, PIC, branch, project | `created_by`, imported timestamps, follow-up, qualification, and existing Team are preserved unless a deterministic final Team is resolved |
| Lead activity | `sales_lead_status_histories.id` | `legacy_migration_maps` → `lead_activities.id` | Insert once | Map reuse | Imported history is immutable | No duplicate history rows |
| Agenda | `content_items.id` | `legacy_migration_maps` → `sales_agendas.id` | Insert once | Map reuse | CRM agenda fields and owner | Existing `team_id` is preserved; new rows use the owner's final Team |
| Agenda assignment | `content_items.id` | `legacy_migration_maps` → assignment history | Insert once | Map reuse | No history rewrite | Assignment history is immutable |
| Agenda evidence | `sales_agenda_evidences.id` | `legacy_migration_maps` → evidence row | Insert once | Map reuse | New/changed evidence can be copied with `--copy-evidence` | Existing mapping prevents duplicate rows |

Source deletions are not automatically propagated. A deleted CRM Lead is
excluded from the active source query and remains in OASIS Next for review and
retention purposes.

## Delta detection

The importer uses source primary keys plus source timestamps. It considers
`updated_at`, `created_at`, and for status history `changed_at`. A map stores the
last source timestamp synchronized. Consequently, repeating the exact same
delta window performs no target update and creates no duplicate row.

Rows without a usable timestamp are reported and processed by stable source ID;
this is safer than using a target row count. If timestamps are not reliable in a
source export, use a consistent source snapshot/export and retain its checksum
as the cutover artifact.

## Lead Team policy

For a new delta Lead, the importer resolves:

```text
source sales_user_id
→ mapped OASIS Next user
→ exactly one active Team with final COORD-B* code,
   Sales membership, and Team-scoped Sales role
```

The Team Branch must equal the Lead Branch. If no deterministic Team exists, the
Lead is imported without an inferred Team and the report contains a blocking
exception. An existing Lead keeps its current Team when the new Sales PIC has
no deterministic final Team. It is never moved to an unrelated legacy Team.

Delta mode intentionally does not update `reports_to_id`, legacy Team
memberships, or legacy Team roles. Organization changes must be handled by a
fresh authoritative Coordinator → Sales mapping and the separate Team
normalization command before the final Lead audit.

## Production source verification status

The live source must be checked over the approved production access path before
using this command. If the connection resolves to a placeholder database, or
source identity cannot be proven against the previous Branch/user/Lead mapping,
stop. Do not run a delta against a merely schema-compatible database.

## Field ownership

| Ownership | Fields |
| --- | --- |
| CRM-authoritative until freeze | Lead contact/business fields, source, channel/activity, campaign, lead date, project, branch, Sales PIC, status, and notes |
| OASIS Next-derived | normalized `team_id`, existing follow-up fields, qualification fields, Team memberships, Team roles, and audit metadata |
| Immutable imported history | Lead primary identity, `created_by`, imported `created_at`, Lead activities, and Agenda assignment history |

The source status is still sanitized to the supported pre-process statuses. UTJ
through BAST process data remains excluded under the existing migration policy.
