# Lead ownership cleanup

`php artisan oasis:cleanup-lead-ownership` is a dry-run-first audit for invalid Lead ownership.

```bash
php artisan oasis:cleanup-lead-ownership \
  --cutover-at="2026-10-19 10:00:00" \
  --timezone=Asia/Jakarta
```

The command reports only technical IDs and ownership evidence. It classifies every anomaly as
`VALID_SALES_REPAIRABLE`, `RECOVERED_SALES_OWNER`,
`NEEDS_MANUAL_ASSIGNMENT_CURRENT_MONTH`, `EXCLUDE_FROM_CUTOVER_OLD_UNRESOLVED`, or
`OTHER_BLOCKER`.

Only deterministic repairs and confirmed recovered owners can be applied locally or in staging:

```bash
php artisan oasis:cleanup-lead-ownership --apply \
  --cutover-at="2026-10-19 10:00:00" --timezone=Asia/Jakarta
```

Production apply additionally requires `--confirm=CLEANUP-OASIS-NEXT-LEAD-OWNERSHIP` and is not
part of the rehearsal workflow. The command never deletes a legacy CRM record. Old unresolved
Leads receive an explicit exclusion disposition; current-month unresolved Leads remain visible
for manual assignment. Re-running the apply is idempotent.

The current-month boundary is derived from the supplied cutover timestamp in the supplied
timezone. It is not hard-coded to October 2026.
