# OASIS CRM to OASIS Next Cutover Runbook

This runbook is preparation-only until the production approval gates are
explicitly signed. Every command is dry-run unless `--apply` is supplied.

## Before the window

1. Approve the final Coordinator → Sales mapping and record its SHA-256.
2. Resolve the one active unmapped Sales user and the two inactive Sales
   decisions, or document why each is outside operational scope.
3. Disposition the 36 existing Lead review records. Do not guess their Team.
4. Confirm backups for both databases and preserve the backup checksums.
5. Confirm the legacy source connection points to the intended source snapshot.
   Verify the database/schema identity, Branch codes, known source IDs, user
   mappings, and existing migration maps. A schema match alone is insufficient.
6. Choose one timezone and one freeze timestamp. Do not use a moving `now`.
7. Announce that OASIS CRM writes will stop at the freeze timestamp.

## Freeze and final synchronization

1. At the approved timestamp, enable the legacy CRM's application-level
   read-only switch. This switch is not implemented or enabled by this OASIS
   Next repository; it must be added and tested in the separate legacy CRM
   application. Login, sessions, cache, logs, reports, search, and downloads
   must remain available.
2. Record the actual freeze timestamp, timezone, source backup/export checksum,
   and operator.
3. Generate a fresh authoritative organization mapping from
   `sales_coordinator_sales`.
4. Run the delta importer in dry-run mode:

   ```bash
   php artisan migration:import-oasis \
     --source=legacy_oasis \
     --delta \
     --since="PREVIOUS_SYNC_IN_SOURCE_TIMEZONE" \
     --freeze-at="FREEZE_TIMESTAMP_IN_SOURCE_TIMEZONE" \
     --timezone=Asia/Jakarta \
     --report=storage/app/audits/crm-delta-final-dry-run.json
   ```

5. Stop if the report contains source rows after freeze, missing mappings,
   ambiguous Teams, or source/target reconciliation differences that are not
   explained.
6. Apply the approved delta using the same timestamps and report path family:

   ```bash
   php artisan migration:import-oasis \
     --source=legacy_oasis \
     --delta --apply \
     --since="PREVIOUS_SYNC_IN_SOURCE_TIMEZONE" \
     --freeze-at="FREEZE_TIMESTAMP_IN_SOURCE_TIMEZONE" \
     --timezone=Asia/Jakarta \
     --confirm=IMPORT-OASIS-CRM \
     --report=storage/app/audits/crm-delta-final-apply.json
   ```

7. Run the authoritative Team normalization dry-run with the fresh mapping.
8. Review and apply Team normalization only after approval. Keep legacy Teams
   active during verification; do not deactivate or delete them.
9. Run `oasis:reassign-lead-teams` dry-run and apply only the deterministic
   approved records. That command changes only `team_id`.
10. Run the organization audit and compare the report with the approved
    exception list.

## Reconciliation and smoke checks

Require the delta report to explain:

```text
SOURCE CRM: total at freeze, created since previous sync, updated since previous sync
OASIS NEXT: total, mapped, newly imported, updated, unchanged, exceptions
```

Then verify:

- each new Lead exists exactly once by `CRM-{source id}`;
- each new activity exists exactly once by its migration map;
- updated status and Sales PIC values are present;
- a Sales PIC change follows exactly one deterministic final Team;
- Next-only Team, follow-up, qualification, creator, and history data remain;
- no cross-Branch Lead, Team, membership, or Agenda exists;
- Sales dashboard, Lead creation/update, Coordinator dashboard, Agenda create,
  Agenda completion, and Coordinator redirect smoke checks pass;
- the exact same delta command produces zero new rows and zero unnecessary
  target mutations.

## Failure and rollback decision

If verification fails:

1. Keep OASIS Next operational writes closed.
2. Keep OASIS CRM read-only.
3. Preserve all reports, mappings, backups, and checksums.
4. Choose formally between repair-forward and restoring the approved backup.
5. Do not casually reopen writes in both systems; that creates split-brain
   ownership and invalidates the timestamped reconciliation.

## Go/no-go

The cutover is **NO-GO** if an active Sales user lacks an approved mapping, a
Lead requires guessed ownership, a source row is newer than the freeze, a
cross-Branch relationship exists, or the final counts are unexplained. Historical
exceptions may remain only when they are explicitly listed, do not affect active
operations, and are accepted by the business owner.
