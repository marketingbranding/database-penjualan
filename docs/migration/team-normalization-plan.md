# Team Normalization and Cutover Migration Plan

This plan is for a future implementation phase. The current audit does not
modify Teams, memberships, Leads, Agendas, or permissions.

## Cutover principle

```text
Before freeze
OASIS CRM = operational source
OASIS Next = preparation and verification

Freeze timestamp
↓
Final synchronization / normalization
↓
Read-only verification
↓
Coordinator + Sales start using OASIS Next
↓
OASIS CRM becomes read-only archive
```

Do not operate both systems as writable sources after the freeze. The final
migration must have one owner, one timestamp, one report, and one rollback
decision.

## Phase 1 — Freeze source and capture evidence

1. Define the exact CRM freeze timestamp and timezone.
2. Stop new operational writes in OASIS CRM.
3. Take a source backup/export and preserve its checksum.
4. Run the existing legacy import in dry-run mode.
5. Run `oasis:audit-organization` before any normalization.
6. Save the source export, import report, audit report, and mapping decisions
   as deployment artifacts outside the application repository.

## Phase 2 — Build the final Coordinator Teams

The production forensic review found that the legacy `sales_coordinator_sales`
pivot is the authoritative current membership source. It contains 56 valid
rows for 16 Coordinators and 56 distinct Sales users. Use those rows as the
initial proposal; do not infer membership from `supervisor_user_id`, empty
legacy `teams` rows, alphabetical order, or same-Branch membership. One active
Sales user remains unassigned and requires a business decision before cutover.

For each real Coordinator:

1. Create or select one final active Team in the correct Branch.
2. Assign the final project context when the Team is project-specific.
3. Set `coordinator_id` to the Coordinator user.
4. Set `supervisor_id` only when the branch hierarchy has confirmed it.
5. Provision the Team-scoped Coordinator role.
6. Assign only the confirmed Sales members from the legacy pivot to the Team.
7. Mark one membership as default for users who legitimately have one Team.

Do not split a legacy Team based only on alphabetical order, current row order,
or same-Branch membership. The current audit found no reliable
`reports_to_id` evidence for most mappings, so ambiguous assignments require a
business-owner decision or a trusted source mapping.

## Phase 3 — Validate memberships and hierarchy

For every Coordinator and Sales user, confirm:

```text
user.branch_id = Team.branch_id
Team is active
Coordinator is a Team member
Coordinator has the Team Coordinator role
Sales is a Team member
Sales has the Team Sales role
Sales reports_to_id is either the confirmed Coordinator or intentionally blank
```

A Team must have one accountable Coordinator for operational cutover. If a
Coordinator legitimately owns multiple Teams, document each Team explicitly and
verify that the active-Team selector cannot choose another Branch.

## Phase 4 — Reconcile operational records

### Leads

1. Build a deterministic mapping from each legacy Team/container and Sales PIC
   to a final Team.
2. For each Lead, set `team_id` to the final Team of its `sales_pic_id`.
3. Confirm `branch_id` matches both the Sales PIC and Team.
4. Preserve `id`, `id_lead`, activities, timestamps, `created_by`, and audit
   history.
5. Quarantine unresolved records instead of guessing.
6. Re-run the organization audit and require zero critical Lead mismatches.

### Agendas

1. Map `owner_id` to the final Team of the owner.
2. Preserve assignment histories and audit records.
3. For linked Leads, require Agenda Team and Lead Team to agree.
4. For unlinked Agendas, use the owner mapping and flag any unresolved case.
5. Verify Coordinator redirect permissions after normalization.

### Sales Cases and process modules

Sales Cases currently do not contain `team_id` or `sales_pic_id`. Do not add a
new ownership field as part of this audit. Instead, document and verify the
existing project-branch and linked Lead/Agenda rules first. A schema change is
only justified if the process workflow proves that branch context is
insufficient after Team normalization.

## Phase 5 — Disable legacy containers safely

1. Keep legacy Teams and their IDs during the verification window.
2. Mark them inactive only after all users and operational records are mapped.
3. Do not delete them immediately; migration maps, audit history, and old
   foreign keys must remain traceable.
4. Restrict the selector to final active Teams for Coordinator and Sales users.
5. Retain a documented legacy-to-final Team mapping.
6. Delete or archive legacy containers only after a successful production
   verification and agreed retention period.

## Phase 6 — Post-migration verification

Run all of the following before enabling production use:

```bash
php artisan oasis:audit-organization --json --report=storage/app/audits/organization-audit-post-cutover.json
php artisan test --compact
```

Require:

- zero critical organization issues;
- zero Lead branch/Team/Sales PIC mismatches;
- zero Agenda owner/Team/Branch mismatches;
- every active Coordinator has the intended Team scope;
- every active Sales user has the intended Team scope;
- no cross-Branch Team membership;
- dashboard and create/update/complete/redirect smoke tests pass.

## Rollback and recovery

Rollback is a data-and-access decision, not merely a code deploy:

1. Keep the pre-normalization database backup and source export immutable.
2. Keep a row-level mapping of every Team, Lead, and Agenda reassignment.
3. If verification fails, stop OASIS Next writes and keep OASIS CRM archived/read-only
   until the incident owner decides whether to restore or repair forward.
4. Never delete legacy Teams or overwrite ownership history as a first response.
5. Record the decision, affected report IDs, and the next freeze window.
