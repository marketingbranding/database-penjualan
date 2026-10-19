# OASIS CRM → OASIS Next Cutover-Day Checklist

This checklist is preparation-only. Do not execute it until the final sign-off is
`READY_FOR_CUTOVER`, the business owner approves the window, and the source and
target production connections are verified. Every placeholder must be replaced
with a recorded value before execution.

## Required records and roles

- **Cutover owner:** ______________________________
- **CRM operator:** ______________________________
- **OASIS Next operator:** ______________________________
- **Business approver:** ______________________________
- **Rollback decision owner:** ______________________________

- [ ] Cutover owner, rollback owner, source CRM operator, target operator, and smoke-test operators recorded.
- [ ] Approved freeze timestamp and timezone recorded. Use one fixed timestamp for every command.
- [ ] Previous successful sync timestamp recorded in `Asia/Jakarta`.
- [ ] Fresh Coordinator → Sales mapping generated from `sales_coordinator_sales` and SHA-256 recorded.
- [ ] Approved historical exception list attached to the change record.
- [ ] Production source identity verified: host, database/schema, Branch codes, user IDs, and migration-map boundary.
- [ ] Production target identity verified: intended OASIS Next database and application instance.

## Abort conditions

Stop immediately before opening OASIS Next if any condition below occurs:

- [ ] CRM read-only cannot be enforced or write rejection cannot be proven.
- [ ] Source or target backup is unavailable, incomplete, or checksum verification fails.
- [ ] Organization mapping is ambiguous or differs unexpectedly from the approved mapping.
- [ ] A current-month Lead has unresolved PIC ownership.
- [ ] Operational Lead blockers are greater than zero.
- [ ] Operational Agenda blockers are greater than zero.
- [ ] Any unexpected cross-Branch authorization exposure appears.
- [ ] Final delta reports unexplained duplicates, skipped required rows, or reconciliation differences.
- [ ] Required current evidence cannot be migrated and verified.
- [ ] Sales or Coordinator production smoke testing fails.

Approved historical exceptions are not abort conditions when their counts and
classifications remain unchanged.

## GO / ABORT gates

### Gate A — Freeze successful

- [ ] **GO only if:** CRM read-only is active, GET/login/report paths work, one controlled business mutation is rejected, and the freeze timestamp is recorded.
- [ ] **ABORT if:** Any CRM write succeeds, reads fail, JSON mutation rejection is absent, or the timestamp is not fixed.

### Gate B — Source bundle verified

- [ ] **GO only if:** DB dump checksum is valid, evidence archive checksum is valid, evidence manifest is present, and final source counts are recorded.
- [ ] **ABORT if:** Any artifact is unreadable, checksum fails, evidence is missing, or source counts are unavailable.

### Gate C — Migration dry-run

- [ ] **GO only if:** No unexplained current operational blocker exists, no duplicate mapping exists, and current-month Lead PIC problems are zero or explicitly resolved.
- [ ] **ABORT if:** Mapping is ambiguous, a current-month PIC is unresolved, post-freeze rows appear, or the dry-run has unexplained changes.

### Gate D — Post-migration

- [ ] **GO only if:** Lead blockers are `0`, Agenda blockers are `0`, the organization audit is acceptable, and required evidence validation passes.
- [ ] **ABORT if:** Any operational blocker, unexpected authorization exposure, reconciliation difference, or evidence failure remains.

### Gate E — Production smoke

- [ ] **GO LIVE only if:** Sales login/scope passes, Coordinator login/scope passes, no cross-Team exposure appears, and Lead PIC rules are correct.
- [ ] **ABORT if:** Any smoke scenario fails or user scope is broader than the approved Team.

## 1. Backups and source identity

### Snapshot-based CRM export commands (preparation only)

These commands are not executed during infrastructure verification. They use
the production database on `localhost` and do not expose a password. The
cutover operator must verify the approved MySQL client credential file path
before execution.

- [ ] **Prerequisite:** CRM is read-only, SSH access is authenticated, the database identity is verified, and the approved MySQL credential file exists on the CRM host.
- [ ] Create the final CRM dump on the CRM host:

  ```bash
  ssh -p 65002 u367554606@145.223.108.212 \
    "mysqldump --defaults-extra-file=/home/u367554606/.my.cnf \
      --single-transaction --quick --routines --triggers --events \
      --host=localhost --databases u367554606_oasis_db \
      | gzip -c > /home/u367554606/oasis-export/oasis-production-YYYYMMDDTHHMMSSZ.sql.gz"
  ```

- [ ] Create the evidence archive on the CRM host:

  ```bash
  ssh -p 65002 u367554606@145.223.108.212 \
    "tar -czf /home/u367554606/oasis-export/oasis-evidence-YYYYMMDDTHHMMSSZ.tar.gz \
      -C /home/u367554606/domains/oasis.marison.id/storage/app/public \
      sales-agenda-evidence"
  ```

- [ ] Record the evidence manifest, snapshot metadata, database inventory, and SHA-256 values using the approved CRM export procedure.
- [ ] Transfer the bundle to `<CUTOVER_EXECUTION_HOST>` using the approved secure transfer path. Do not use public object storage or an unencrypted transfer.
- [ ] **Expected output:** Final SQL dump, evidence archive, manifest, metadata, inventory, and checksum file exist at the cutover host with matching hashes.
- [ ] **Abort condition:** SSH authentication fails, database identity is uncertain, credential file is absent, transfer destination is unknown, or any checksum fails.

### CRM production backup

- [ ] **Prerequisite:** Approved backup operator, production access, enough storage, and an agreed artifact naming convention are available.
- [ ] Complete the approved CRM backup procedure. This repository does not implement a backup command; do not invent or substitute one here.
- [ ] **Expected output:** Backup artifact is complete, restorable, and has a recorded checksum, timestamp, database identity, and operator.
- [ ] **Abort condition:** Backup fails, is partial, cannot be restored/verified, or checksum is missing.

### OASIS Next production backup

- [ ] **Prerequisite:** Approved target backup operator and target database identity are confirmed.
- [ ] Complete the approved OASIS Next backup procedure.
- [ ] **Expected output:** Target backup artifact is complete, restorable, and has a recorded checksum, timestamp, database identity, and operator.
- [ ] **Abort condition:** Backup fails, target identity is uncertain, or checksum is missing.

### Temporary source restore

- [ ] **Prerequisite:** Source bundle checksums pass; cutover execution host and target database identity are confirmed; target and temporary source names are different.
- [ ] Create/restore `oasis_legacy_cutover_YYYYMMDD` on the cutover host using the approved SELECT-capable source credential:

  ```bash
  mysql --defaults-extra-file=/path/to/cutover-source.cnf \
    --host=127.0.0.1 \
    -e "CREATE DATABASE oasis_legacy_cutover_YYYYMMDD CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

  gzip -dc oasis-production-YYYYMMDDTHHMMSSZ.sql.gz \
    | mysql --defaults-extra-file=/path/to/cutover-source.cnf \
      --host=127.0.0.1 oasis_legacy_cutover_YYYYMMDD
  ```

- [ ] Set the cutover environment outside Git:

  ```dotenv
  LEGACY_DB_CONNECTION=mysql
  LEGACY_DB_HOST=127.0.0.1
  LEGACY_DB_DATABASE=oasis_legacy_cutover_YYYYMMDD
  LEGACY_DB_USERNAME=<cutover-source-user>
  LEGACY_DB_PASSWORD=<secret-from-secret-store>
  ```

- [ ] **Expected output:** Temporary source restores successfully, required tables/counts match the final source bundle, and source/target safety guards pass.
- [ ] **Abort condition:** Source database equals target database, restore is incomplete, table inventory differs unexpectedly, or credentials would be committed/exposed.

### Source and target baseline

- [ ] **Prerequisite:** Both backups are verified and the source connection is SELECT-only for this step.
- [ ] Record active Lead count, newest Lead `created_at`, newest Lead `updated_at`, active Sales count, Coordinator count, valid `sales_coordinator_sales` count, active unassigned Sales count, active Agenda count, and required evidence count.
- [ ] **Expected output:** Baseline is attached to the change record and matches the approved source/target identity.
- [ ] **Abort condition:** Counts cannot be collected, source identity is a placeholder, or any unexplained organization change is found.

## 2. Freeze CRM writes

### Enable CRM read-only

- [ ] **Prerequisite:** Baselines and backups are recorded; business owner authorizes the freeze timestamp.
- [ ] Enable the legacy CRM application's approved application-level read-only switch. The switch is external to this repository; no repository command is provided.
- [ ] **Expected output:** CRM reads, sessions, reports, search, and downloads remain available while business writes are rejected.
- [ ] **Abort condition:** Read-only cannot be enabled or its scope is unclear.

### Verify write rejection

- [ ] **Prerequisite:** Read-only is enabled and a controlled non-destructive write test is approved.
- [ ] Perform the source application's approved write-rejection test; do not create business data.
- [ ] **Expected output:** The write is rejected and the rejection is logged; normal reads still work.
- [ ] **Abort condition:** Any business write succeeds or reads are unavailable.

### Record freeze

- [ ] Record the actual freeze timestamp, timezone, operator, source backup checksum, and target backup checksum.
- [ ] **Expected output:** One immutable freeze record is available to every operator.
- [ ] **Abort condition:** Freeze timestamp is moving, ambiguous, or differs between operators.

### Read-only implementation verification

- [ ] **Prerequisite:** Authenticated read-only inspection access to the CRM application path is available.
- [ ] Inspect the deployed CRM code for `OASIS_READ_ONLY`, server-side mutation guard, UI/banner support, JSON HTTP 423 behavior where implemented, and allowed login/view/report paths.
- [ ] Record deployed commit/version and compare it with the tested read-only implementation commit.
- [ ] **Expected output:** `READ_ONLY_CODE_DEPLOYED=YES`, deployed version is known, and the live value remains `false` before the window.
- [ ] **Abort condition:** Code is absent, version is unknown/diverged, mutation guard is missing, or live value cannot be verified. Do not deploy automatically.

## 3. Generate mapping and run final delta

### Fresh mapping

- [ ] **Prerequisite:** CRM is read-only and the source identity is verified.
- [ ] Generate the authoritative Coordinator → Sales mapping from `sales_coordinator_sales` using the approved source audit procedure.
- [ ] **Expected output:** Every operational Sales user has exactly one valid Coordinator mapping; approved exclusions are explicit; mapping SHA-256 is recorded.
- [ ] **Abort condition:** Ambiguous mapping, unexpected Branch mismatch, duplicate active assignment, or changed disposition for Sales IDs 76, 94, or 95.

### Delta dry-run

- [ ] **Prerequisite:** Mapping, previous sync timestamp, freeze timestamp, and source storage/evidence path are recorded.
- [ ] Run the implemented dry-run command:

  ```bash
  php artisan migration:import-oasis \
    --source=legacy_oasis \
    --delta \
    --since="PREVIOUS_SYNC_IN_SOURCE_TIMEZONE" \
    --freeze-at="FREEZE_TIMESTAMP_IN_SOURCE_TIMEZONE" \
    --timezone=Asia/Jakarta \
    --report=storage/app/audits/crm-delta-final-dry-run.json
  ```

- [ ] **Expected output:** No source rows after freeze; no unexplained ambiguity, skipped required rows, duplicates, evidence failures, or reconciliation differences.
- [ ] **Abort condition:** Any unexplained blocker, duplicate, missing mapping, post-freeze row, or required evidence failure.

### Final source counts

- [ ] Record final active Leads, max Lead ID, newest `created_at`, newest `updated_at`, active Sales, Coordinators, current valid Coordinator → Sales mappings, active unassigned Sales, and evidence count after read-only activation and before export.
- [ ] **Expected output:** These are recorded as `FINAL SOURCE COUNTS`; rehearsal counts are not reused as final counts.
- [ ] **Abort condition:** Any required count or timestamp is unavailable.

### Review delta

- [ ] Compare source totals at freeze with target totals, created rows, updated rows, unchanged rows, exceptions, and evidence results.
- [ ] Confirm historical exceptions remain unchanged and are not treated as new blockers.
- [ ] **Expected output:** Written reviewer approval for the exact dry-run report and mapping hash.
- [ ] **Abort condition:** Review is incomplete or any difference is unexplained.

### Apply final delta

- [ ] **Prerequisite:** Dry-run approval is recorded; CRM remains read-only; backups and freeze record are complete.
- [ ] Run the implemented apply command with the same timestamps:

  ```bash
  php artisan migration:import-oasis \
    --source=legacy_oasis \
    --delta \
    --apply \
    --since="PREVIOUS_SYNC_IN_SOURCE_TIMEZONE" \
    --freeze-at="FREEZE_TIMESTAMP_IN_SOURCE_TIMEZONE" \
    --timezone=Asia/Jakarta \
    --confirm=IMPORT-OASIS-CRM \
    --copy-evidence \
    --report=storage/app/audits/crm-delta-final-apply.json
  ```

- [ ] **Expected output:** Apply report matches the approved dry-run; source rows are not written; no unexplained blocking issue is reported.
- [ ] **Abort condition:** Apply report differs materially from dry-run, reports a blocker, or evidence copy/reconciliation fails.

## 4. Normalize Teams and ownership

### Team normalization dry-run

- [ ] **Prerequisite:** Fresh mapping file and SHA-256 are recorded.
- [ ] Run:

  ```bash
  php artisan oasis:normalize-teams \
    --mapping="AUTHORITATIVE_MAPPING_PATH" \
    --report=storage/app/audits/team-normalization-final-dry-run.json
  ```

- [ ] **Expected output:** No preflight errors; all operational Coordinator/Sales mappings are deterministic; approved exclusions only; legacy Teams remain retained.
- [ ] **Abort condition:** Any preflight error, readiness blocker outside the approved exception list, duplicate membership, or unexpected Team/Branch change.

### Team normalization apply

- [ ] **Prerequisite:** Dry-run report approved; target backup verified; CRM remains read-only.
- [ ] Run:

  ```bash
  php artisan oasis:normalize-teams \
    --mapping="AUTHORITATIVE_MAPPING_PATH" \
    --apply \
    --confirm=NORMALIZE-OASIS-NEXT-TEAMS \
    --report=storage/app/audits/team-normalization-final-apply.json
  ```

- [ ] **Expected output:** Apply report matches dry-run and records the mapping SHA-256. Do not pass `--deactivate-legacy` during this sequence.
- [ ] **Abort condition:** Apply fails, mapping hash changes, or final Team counts/memberships differ unexpectedly.

### Lead ownership cleanup dry-run

- [ ] **Prerequisite:** Team normalization apply is successful and cutover timestamp is fixed.
- [ ] Run:

  ```bash
  php artisan oasis:cleanup-lead-ownership \
    --cutover-at="FREEZE_TIMESTAMP_IN_SOURCE_TIMEZONE" \
    --timezone=Asia/Jakarta \
    --report=storage/app/audits/lead-ownership-cleanup-final-dry-run.json
  ```

- [ ] **Expected output:** Current-month unresolved PIC count is zero; approved historical exceptions are classified explicitly; no unexplained current ownership repair is proposed.
- [ ] **Abort condition:** Current-month unresolved PIC, ambiguous repair, or unexplained classification.

### Lead ownership cleanup apply

- [ ] **Prerequisite:** Dry-run is approved and the target backup remains available.
- [ ] Run:

  ```bash
  php artisan oasis:cleanup-lead-ownership \
    --cutover-at="FREEZE_TIMESTAMP_IN_SOURCE_TIMEZONE" \
    --timezone=Asia/Jakarta \
    --apply \
    --confirm=CLEANUP-OASIS-NEXT-LEAD-OWNERSHIP \
    --report=storage/app/audits/lead-ownership-cleanup-final-apply.json
  ```

- [ ] **Expected output:** Only deterministic repairs and approved dispositions are applied; current operational blockers remain zero.
- [ ] **Abort condition:** Any non-deterministic repair, current-month unresolved PIC, or unexpected change count.

### Lead Team reassignment dry-run

- [ ] **Prerequisite:** Team normalization and ownership cleanup reports are approved; mapping hash is unchanged.
- [ ] Run:

  ```bash
  php artisan oasis:reassign-lead-teams \
    --mapping="AUTHORITATIVE_MAPPING_PATH" \
    --report=storage/app/audits/lead-team-reassignment-final-dry-run.json
  ```

- [ ] **Expected output:** Only `SAFE_REASSIGN` records are proposed; approved exceptions and unresolved records remain untouched.
- [ ] **Abort condition:** Any `NEEDS_REVIEW` record is proposed for mutation, or the report contains unexplained preflight errors.

### Lead Team reassignment apply

- [ ] **Prerequisite:** Dry-run is approved and only safe deterministic changes are present.
- [ ] Run:

  ```bash
  php artisan oasis:reassign-lead-teams \
    --mapping="AUTHORITATIVE_MAPPING_PATH" \
    --apply \
    --confirm=REASSIGN-OASIS-NEXT-LEAD-TEAMS \
    --report=storage/app/audits/lead-team-reassignment-final-apply.json
  ```

- [ ] **Expected output:** Only approved `team_id` changes are applied; report status is successful; no approved exception is altered.
- [ ] **Abort condition:** Any non-safe change, duplicate, Branch mismatch, or unexplained mutation.

## 5. Final audit and reconciliation

### Organization audit

- [ ] **Prerequisite:** Delta, Team, ownership, and reassignment reports are successful.
- [ ] Run:

  ```bash
  php artisan oasis:audit-organization \
    --json \
    --report=storage/app/audits/organization-audit-final.json
  ```

- [ ] **Expected output:** Operational Lead blockers `0`; operational Agenda blockers `0`; deterministic mappings; no cross-Branch unauthorized memberships; no invalid operational Coordinator PIC; approved exceptions remain visible.
- [ ] **Abort condition:** Any critical blocker, unexpected mapping change, duplicate, or authorization exposure.

### Source/target reconciliation

- [ ] Reconcile source and target Lead, activity, Agenda, assignment, evidence, user, Team, membership, and migration-map counts.
- [ ] Repeat the exact delta dry-run using the same fixed timestamps.
- [ ] **Expected output:** No new rows, no unnecessary target mutations, and no unexplained count differences.
- [ ] **Abort condition:** Repeat run is not idempotent or any count differs without an approved explanation.

## 6. Read-only-first smoke tests

Prefer read-only checks. Do not create production data unless the business owner
explicitly approves a controlled record and its cleanup plan.

### Sales account

- [ ] **Prerequisite:** Final audit and reconciliation pass; approved production Sales account selected; credentials are not modified.
- [ ] Login.
- [ ] Open dashboard.
- [ ] View own Leads and one authorized Lead detail.
- [ ] View Agenda and existing evidence.
- [ ] Confirm foreign Lead/Agenda access is denied.
- [ ] **Expected output:** Correct own scope, no cross-Team exposure, no runtime errors.
- [ ] **Abort condition:** Login, scope, evidence, or authorization failure.

### Coordinator account

- [ ] **Prerequisite:** Final audit passes; approved production Coordinator account selected; credentials are not modified.
- [ ] Login.
- [ ] Confirm active Team scope and Sales visibility.
- [ ] Confirm Lead ownership scope and read-only Agenda/evidence visibility.
- [ ] Confirm Coordinator is not selectable as a Sales PIC.
- [ ] **Expected output:** Correct Team scope and no cross-Branch exposure.
- [ ] **Abort condition:** Scope, ownership, evidence, or PIC-selection failure.

## 7. Open OASIS Next and hold CRM read-only

- [ ] **Prerequisite:** All prior sections pass and the change owner signs the go decision.
- [ ] Open OASIS Next for normal user access.
- [ ] Keep OASIS CRM read-only.
- [ ] Record opening timestamp and operator.
- [ ] **Expected output:** Users operate only in OASIS Next; CRM remains available for read-only reference.
- [ ] **Abort condition:** Any production smoke failure, unexpected write path, split-brain risk, or rollback uncertainty.

## 8. Rollback

- [ ] If failure occurs before normal OASIS Next writes, keep both systems controlled and prefer repair/rollback while CRM remains read-only.
- [ ] If rollback to CRM is approved, close OASIS Next writes first.
- [ ] Explicitly restore CRM write access using the source application's approved procedure.
- [ ] Record rollback timestamp, operator, reason, backup used, and final system state.
- [ ] Never leave both CRM and OASIS Next writable simultaneously.
- [ ] Before OASIS Next opens for normal writes: keep CRM read-only; repair forward or restore the verified OASIS Next backup; rerun audit and reconciliation.
- [ ] For formal rollback to CRM: disable OASIS Next writes, confirm no Next writes require reconciliation, restore/verify Next as needed, set `OASIS_READ_ONLY=false` through the approved CRM deployment mechanism, verify CRM writes, and record the rollback timestamp.

## Final sign-off

- [ ] Final readiness document says `READY_FOR_CUTOVER`.
- [ ] No abort condition is open.
- [ ] Change owner signs go.
- [ ] Rollback owner confirms availability.
- [ ] All reports, mappings, backups, checksums, and smoke results are archived.
