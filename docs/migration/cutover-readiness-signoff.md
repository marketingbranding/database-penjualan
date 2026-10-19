# Final Cutover Readiness Sign-off

**Assessment date:** 2026-10-19  
**Decision:** `NOT_READY_FOR_CUTOVER`  
**Scope:** Rehearsal/staging verification only. No production write, migration, freeze, or read-only change was executed.

## Executive decision

The migration implementation, rehearsal, organization normalization, and Coordinator + Sales UAT are technically successful. The final production freshness check could not be completed safely because the configured `legacy_oasis` connection resolves to the local placeholder database `127.0.0.1/laravel`, not a verified production CRM source.

Therefore the current decision is **NOT_READY_FOR_CUTOVER**. The decision must be revisited only after the approved production read-only connection, current source counts, source mapping, backup artifacts, and freeze procedure are verified.

## Migration rehearsal status

| Area | Result | Evidence |
| --- | --- | --- |
| Full importer | PASS in isolated rehearsal | Final rehearsal contained 6,204 Leads, 8,075 Agendas, 1,224 evidence rows, 91 users, and 29,886 migration maps. |
| Full importer idempotency | PASS | Repeat run produced zero business inserts, updates, organization changes, or evidence copies. The proof harness observed only three cache-maintenance writes. |
| Delta importer | PASS in isolated rehearsal | Repeat delta window reported no ambiguous/skipped/warning rows and no blocking issues; 0 new and 0 updated target rows. |
| Delta idempotency | PASS | Repeat run retained existing records and produced no duplicate target records or unnecessary business mutations. |
| Evidence migration | PASS in rehearsal | 1,224 evidence rows/files were retained; repeat import reported zero evidence inserts/updates and 1,224 unchanged. |
| Team normalization | PASS in rehearsal | 16 final Coordinator Teams were mapped; 56 active Sales memberships were deterministic; nine legacy Teams were retained for verification. |
| Lead ownership | PASS for operational scope | Final audit reports 0 operational Lead blockers; approved historical exceptions remain visible in the audit. |
| Agenda ownership | PASS for operational scope | Final audit reports 0 operational Agenda blockers; approved historical Agenda exceptions remain visible in the audit. |
| UAT | PASS | Coordinator + Sales UAT passed after two evidence defects were fixed and retested. See `docs/uat/coordinator-sales-uat.md`. |

## Final rehearsal organization audit

Command executed against the cleaned isolated rehearsal database:

```bash
php artisan oasis:audit-organization
```

The command reported that it is read-only and modified no business records.

| Check | Result |
| --- | ---: |
| Operational Lead blockers | **0** |
| Operational Agenda blockers | **0** |
| Coordinator mappings | **16 confirmed / 16 total** |
| Deterministic active Sales memberships | **56** |
| Cross-Branch Team memberships | **0** |
| Invalid operational Coordinator PICs | **0** |
| Duplicate Team codes | **0** |
| Duplicate `team_user` memberships | **0** |
| Duplicate migration maps | **0** |

The audit still reports non-blocking warnings for retained legacy Teams without a Coordinator or project and two anonymized Team members. These are not silently hidden; they remain documented review items. The audit also reports that Sales Cases have no native Team owner field, which is an informational model limitation rather than an operational blocker in this scope.

## Historical exception sign-off

The following dispositions are treated as approved business exceptions and remain visible in the audit:

| Population | Disposition | Verified rehearsal state |
| --- | --- | --- |
| 20 historical cross-Branch Leads | `APPROVED_HISTORICAL_CROSS_BRANCH` | 20 records remain classified as approved exceptions and are excluded from operational blocker counts. |
| 25 historical Agenda mismatches | `APPROVED_HISTORICAL_EXCEPTION` | 25 records remain classified as approved historical Agenda exceptions. |
| 16 old unresolved Coordinator-PIC Leads | `EXCLUDE_FROM_CUTOVER_OLD_UNRESOLVED` | 16 records remain preserved for audit, have `sales_pic_id = NULL`, and are excluded from normal operational Lead views. |
| Source Sales ID 76 | `EXCLUDE_FROM_FIRST_WAVE_GO_LIVE_PENDING_MAPPING` | Last verified source snapshot showed no active Leads, current Agendas, or active Sales Cases. The target mapping exists but current production ownership was not revalidated because the live source connection is unavailable. |
| Source Sales IDs 94/95 | `INACTIVE_NON_OPERATIONAL` | Last verified source snapshot classified both as inactive/non-operational; target rehearsal records own no Leads or Agendas. Current source status still requires freshness verification. |

The rehearsal target still maps source user IDs 76, 94, and 95 to target users, but that mapping does not override the approved source-level dispositions.

## Production freshness check

The specified SSH path was attempted with batch/non-interactive authentication:

```text
ssh -o BatchMode=yes -p 65002 u367554606@145.223.108.212
```

It returned `Permission denied (publickey,password)`. No production shell, SQL,
filesystem mutation, deployment, or configuration change was reached.

The required SELECT-only check through `legacy_oasis` also failed before any
query could run because the local connection still resolves to the placeholder
database:

```text
SQLSTATE[HY000] [1049] Unknown database 'laravel'
Connection: legacy_oasis, Host: 127.0.0.1, Port: 3306, Database: laravel
```

This is a source-identity failure, not evidence that production is unchanged. The following current values are therefore **not available**:

- current active Lead count;
- newest Lead `created_at`;
- newest Lead `updated_at`;
- current active Sales count;
- current Coordinator count;
- current valid `sales_coordinator_sales` mapping count; and
- current active unassigned Sales count.

The last verified production snapshot recorded a raw `sales_leads` count of
6,211, max Lead ID 6,387, newest `created_at` and `updated_at` of
`2026-10-19 16:41:55`, 57 active Sales, 16 Coordinators, 56 valid Coordinator
→ Sales rows, and one active unassigned Sales user. The importer-eligible Lead
count at the same freeze was 6,204. The Lead increase since that snapshot and
whether organization mapping changed are **unknown**, not assumed to be zero.
A final delta is sufficient only after a fresh source check proves that the
organization mapping is unchanged.

## Snapshot transport verification

The retained local copy of the previous production-like export remains present
and was not deleted:

```text
C:\Users\FAIZAL\AppData\Local\Temp\opencode\oasis-export\
```

The bundle contains the SQL dump, evidence archive, evidence manifest, snapshot
metadata, database inventory, and checksum manifest. Verification results:

- SQL gzip stream: readable and valid.
- Evidence gzip stream: readable and valid.
- Evidence tar entries: 1,224.
- Evidence manifest data rows: 1,224.
- SQL dump SHA-256: matches the checksum recorded in the metadata and checksum manifest.
- Evidence archive, manifest, metadata, and inventory SHA-256 values: match.

The checksum manifest names the SQL file with colon separators while the stored
Windows filename uses hyphen separators; the content hash matches after mapping
the timestamp spelling. This naming difference must be normalized in the final
operator bundle. The bundle is prior snapshot evidence, not the final cutover
backup.

The secure transfer, temporary database restore, and cutover-host source guard
could not be verified because SSH authentication and the target host are not
available in this session.

## Cutover execution host and temporary source

```text
CUTOVER_EXECUTION_HOST: UNVERIFIED
OASIS_NEXT_APPLICATION_PATH: UNVERIFIED
OASIS_NEXT_TARGET_DATABASE: UNVERIFIED
TEMPORARY_SOURCE_DATABASE: oasis_legacy_cutover_YYYYMMDD (design only)
TEMPORARY_SOURCE_HOST: 127.0.0.1 on the cutover execution host (design only)
EVIDENCE_SOURCE_PATH: final restored CRM snapshot / sales-agenda-evidence (to verify)
EVIDENCE_TARGET_PATH: OASIS Next public storage disk (to verify)
```

The current rehearsal host is `FaizalPC` running PHP 8.3.31 with the target in
an isolated SQLite file. It is not an approved production cutover host and is
not being treated as one.

The temporary source strategy is therefore **not operationally ready**. It is
ready as a design: source and target must be different databases, `LEGACY_DB_*`
must point to the temporary restored source, and the temporary source must not
be dropped until reconciliation and the rollback decision are complete.

## Legacy read-only implementation and deployed version

`READ_ONLY_CODE_DEPLOYED=UNVERIFIED` (operational answer: **NO**).

The live CRM repository could not be inspected because the specified SSH
authentication failed. The production deployed commit/version and the tested
read-only implementation commit are therefore not discoverable in this
session. No deployment was attempted. The exact required next action is to
restore approved SSH access, compare the deployed commit with the tested CRM
repository commit, and obtain separate deployment approval if the implementation
is absent or diverged.

`OASIS_READ_ONLY_CURRENT_VALUE=false` remains the known, unchanged state; this
session did not enable or modify it. The live value itself is not independently
verified until authenticated access to the CRM application is restored.

## Backup drill status

| Backup | Result |
| --- | --- |
| CRM snapshot procedure | **PASS in prior rehearsal; final cutover backup not created** |
| CRM prior artifact/checksums | **PASS** after stream, archive, manifest, and hash verification described above |
| OASIS Next production backup procedure | **NOT VERIFIED**; production target host/database is unidentified |
| OASIS Next backup drill | **NOT EXECUTED**; no safe target access was available |
| Isolated restore over production | **NOT EXECUTED** |

The cutover checklist contains the required restore/checksum gates. A previous
CRM artifact does not replace a fresh post-freeze CRM backup, and no claim is
made that it does.

## Cutover command sequence verified

The implemented command syntax was verified with each command's `--help`. The exact production sequence is documented in `docs/migration/cutover-day-checklist.md` and is:

1. Complete and checksum the CRM and OASIS Next backups using the approved infrastructure procedure. No backup command is implemented in this repository, so no backup command is invented here.
2. Record source and target counts using the approved SELECT-only production access path.
3. Enable the legacy CRM application's read-only switch using that application's approved operator procedure. This repository does not implement that switch.
4. Verify that CRM business writes are rejected while reads remain available; record the freeze timestamp and timezone.
5. Generate and checksum a fresh authoritative Coordinator → Sales mapping from `sales_coordinator_sales`.
6. Run the final CRM delta dry-run:

   ```bash
   php artisan migration:import-oasis \
     --source=legacy_oasis \
     --delta \
     --since="PREVIOUS_SYNC_IN_SOURCE_TIMEZONE" \
     --freeze-at="FREEZE_TIMESTAMP_IN_SOURCE_TIMEZONE" \
     --timezone=Asia/Jakarta \
     --report=storage/app/audits/crm-delta-final-dry-run.json
   ```

7. Review the delta report; abort on unexplained rows newer than freeze, missing mappings, ambiguity, duplicates, evidence failures, or reconciliation differences.
8. Apply the approved delta with the same timestamps and `--confirm=IMPORT-OASIS-CRM`; use `--copy-evidence` when current evidence is in scope.
9. Run `oasis:normalize-teams` dry-run with the fresh mapping, review it, then apply with `--confirm=NORMALIZE-OASIS-NEXT-TEAMS`. Do not use `--deactivate-legacy` during this sequence.
10. Run `oasis:cleanup-lead-ownership` dry-run with the fixed cutover timestamp, review it, then apply with `--confirm=CLEANUP-OASIS-NEXT-LEAD-OWNERSHIP`.
11. Run `oasis:reassign-lead-teams` dry-run with the same mapping, review it, then apply with `--confirm=REASSIGN-OASIS-NEXT-LEAD-TEAMS` only for safe deterministic changes.
12. Run `php artisan oasis:audit-organization` and reconcile all counts against the approved exceptions.
13. Reconcile source and target counts, then execute read-only-first Sales and Coordinator smoke checks.
14. Open OASIS Next only after all gates pass and keep CRM read-only.

No production command from this sequence was executed during this sign-off.

## Read-only and backup readiness

- `OASIS_READ_ONLY` was not enabled or changed by this session; the known rehearsal state remains false.
- The production CRM read-only mechanism is external to this repository and was not verifiable from the failed SSH path. This is a cutover blocker until tested on the approved production path.
- The prior CRM snapshot bundle is integrity-verified, but current production backup artifacts and OASIS Next backup artifacts/checksums were not verified in this session. These remain pre-cutover gates.

## Known exclusions and defects

### Approved exclusions

- 20 historical cross-Branch Leads.
- 25 historical Agenda mismatches.
- 16 old unresolved Coordinator-PIC Leads.
- Source Sales ID 76 pending mapping decision.
- Source Sales IDs 94/95 inactive/non-operational.
- Retained legacy Team containers during verification.

### UAT defects

No unresolved critical or high UAT defects remain. The two evidence defects found during UAT were fixed, covered by regression tests, and retested successfully.

## Go/no-go criteria

Cutover may move to `READY_FOR_CUTOVER` only when all of the following are recorded for the same freeze window:

- UAT remains `UAT_PASS`.
- Operational Lead and Agenda blockers are both zero.
- Historical exceptions are formally accepted and unchanged.
- Fresh Coordinator/Sales mapping is deterministic, with approved exclusions only.
- Full and delta idempotency reports are retained.
- Production CRM read-only enforcement is tested and working.
- Both production backups and checksums are available.
- Final delta, normalization, cleanup, reassignment, and audit reports reconcile.
- Evidence migration succeeds for required current records.
- Sales and Coordinator smoke tests pass.
- Rollback ownership and timestamps are recorded.

## Rollback principle

If failure occurs before OASIS Next is opened for normal user writes, prefer repair or rollback while CRM remains read-only. Never allow CRM and OASIS Next to become writable simultaneously.

If business chooses rollback to CRM:

1. Close OASIS Next writes.
2. Confirm the target state and preserve all reports and checksums.
3. Explicitly restore CRM write access using the source application's approved procedure.
4. Record the rollback timestamp, operator, and decision.

## Final classification

**`NOT_READY_FOR_CUTOVER`**

Exact blockers:

1. Approved authenticated SSH access to the production CRM source and the cutover execution host is unavailable, so current production freshness and organization mapping cannot be verified.
2. The live CRM read-only implementation/deployed commit cannot be inspected or verified.
3. The OASIS Next production target and its backup procedure/artifacts/checksums cannot be verified.
4. Secure snapshot transfer and restore into the temporary legacy source cannot be verified end-to-end.

No production writes occurred. No production OASIS Next migration occurred. `OASIS_READ_ONLY` was not enabled.
