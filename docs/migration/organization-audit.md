# OASIS Next Organization Audit

## Purpose

This audit is read-only. It checks whether the current Branch, Team, User, role,
Lead, Agenda, and ownership relationships are safe for the first cutover users:
Koordinator Sales and Sales.

Run it with:

```bash
php artisan oasis:audit-organization
php artisan oasis:audit-organization --json --report=storage/app/audits/organization-audit-YYYY-MM-DD.json
```

The JSON report may contain operational user names and must remain outside Git.
The default `storage/app` location is ignored by this repository.

## Canonical model

```text
Branch
└── Team
    ├── coordinator_id → one Coordinator
    ├── users → Coordinator + Sales members
    └── project_id → optional project context, required by current active-Team creation rules
```

Branch Manager, Supervisor, SAS, and SAM/SAP are branch-scoped roles. They do
not need Team membership to obtain branch-wide visibility.

Operational ownership rules checked by the audit:

```text
Lead Team = active Team of Sales PIC
Lead Branch = Sales PIC branch = Team branch
Agenda owner = Team member in the same branch
Agenda Team = linked Lead Team when a Lead is attached
```

## Current audit snapshot

The following is an aggregate snapshot of the current local database audit run.
It contains no personal names or email addresses.

| Metric | Result |
| --- | ---: |
| Branches | 9 |
| Active Teams | 9 |
| Legacy migration Teams | 9 |
| Users | 91 |
| Coordinator Sales role users | 16 |
| Sales role users | 59 |
| Leads | 4,051 |
| Lead Team/ownership mismatches | 20 |
| Agendas | 0 |
| Agenda mismatches | 0 |
| Active Sales Cases | 0 |

All nine active Teams are named as migration containers (`Migrasi Tim ...`),
not final Coordinator Teams. Eight active Teams have no `coordinator_id`; all
nine have no `project_id`.

## Findings

### Critical

1. **Coordinator scope is not configured for 15 of 16 Coordinator role users.**
   Only one active Team has a `coordinator_id`. The remaining Coordinator users
   share branch-level migration containers, so the application cannot safely
   resolve one Coordinator → Sales scope for them.
2. **20 Leads do not match the Sales PIC branch and/or Team membership.**
   These records must be repaired or explicitly dispositioned before the CRM is
   frozen. No automatic correction was performed by this audit.

### Warnings

1. Every active Team is still a legacy migration container.
2. Eight active Teams have no Coordinator pointer.
3. All active Teams have no project assignment.
4. Six Teams contain multiple Coordinator role users, making the intended
   Coordinator → Sales mapping ambiguous.
5. Two anonymized users remain Team members and need a final retention decision.
6. One Team member has no Team-scoped role assignment.

### Informational

1. One branch-scoped role user is also a Team member. This is not required by
   the target organization model and should be reviewed during normalization.
2. Sales Cases currently have no native `team_id` or `sales_pic_id`; process
   scope is inferred through project branch and linked Lead/Agenda context.

## Coordinator mapping result

| Mapping status | Count | Interpretation |
| --- | ---: | --- |
| CONFIRMED | 1 | Team pointer, membership, and Coordinator role agree |
| HIGH CONFIDENCE | 0 | A safe single candidate exists but needs confirmation |
| AMBIGUOUS | 15 | Legacy Team contains multiple possible Coordinators or no pointer |
| UNRESOLVED | 0 | No usable Team evidence exists |

The current data has no useful `reports_to_id` hierarchy evidence for these
Coordinator/Sales mappings. Same-Branch membership in a legacy container is not
enough evidence to split a final Team.

## Sales mapping result

| Mapping status | Count | Interpretation |
| --- | ---: | --- |
| CONFIRMED | 0 | Sales has a single Team and a verified Coordinator hierarchy |
| HIGH CONFIDENCE | 3 | Sales has one Team with a configured Coordinator pointer |
| AMBIGUOUS | 56 | Sales is only attached to a legacy container without a final mapping |
| UNRESOLVED | 0 | Sales has no active Team evidence |

No user belongs to multiple active Teams and no cross-Branch Team memberships
were detected. That is a positive access-control result, but it does not solve
the missing Coordinator partitioning.

## Active Team selector

`SetPermissionTeam` currently exposes every active `team_user` membership to a
user, then chooses the requested, default, or first Team. This is safe against
cross-Branch memberships in the current snapshot, but it exposes legacy Team
containers and cannot express the intended Coordinator-only Team list until
real Teams and `coordinator_id` values exist.

The selector should not be changed during this audit. The smallest safe future
change is to filter Coordinator options by `teams.coordinator_id = user.id`,
while retaining broader branch filtering for future BM/SPV/SAS/SAM access.
