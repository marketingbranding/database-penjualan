# Coordinator + Sales UAT Acceptance Record

**Date:** 2026-10-19  
**Environment:** Isolated rehearsal application and database only  
**Decision:** `UAT_PASS` — cutover remains conditional on the preparation items below

## Scope

The first-wave rehearsal covered Team `COORD-B06-U17` at KC Jepara with:

- Coordinator user ID `17`
- Sales user IDs `16`, `18`, and `19`
- 697 Leads, 650 Agenda records, and 513 retained evidence files in the selected Team scope
- Synthetic records only for create/update/upload scenarios

Production CRM, production OASIS Next, and the `OASIS_READ_ONLY` setting were not changed.

## Scenario results

| Area | Result | Evidence of acceptance |
| --- | --- | --- |
| Sales login and dashboard | PASS | Sales IDs `16`, `18`, and `19` authenticated in rehearsal; dashboard loaded with the selected Team context. |
| Sales Lead access and search | PASS | Sales ID `16` completed Lead listing, search, own Lead detail, create, update, and activity creation. Sales IDs `18` and `19` completed scope/detail smoke checks. |
| Sales Agenda workflow | PASS | Sales ID `16` created an Agenda; Sales ID `18` opened, edited, and completed an Agenda. |
| Coordinator dashboard and monitoring | PASS | Coordinator ID `17` viewed the Team dashboard and Lead monitoring workspace at desktop and mobile sizes. |
| Coordinator Lead workflow | PASS | Coordinator ID `17` created a synthetic Lead assigned to Sales ID `18` and could access Team Leads. |
| Coordinator Agenda workflow | PASS | Coordinator ID `17` created, edited, and completed a synthetic Team Agenda for Sales ID `18`. |
| Evidence history and upload | PASS after fixes | Existing Sales evidence opened successfully; a new synthetic image upload was stored on the public disk and opened by the Coordinator from read-only Agenda detail. |
| Ownership and Team authorization | PASS | Sales ID `16` received HTTP 403 for Sales ID `18`'s Lead; a Sales user received HTTP 403 for a foreign Agenda; Coordinator access remained limited to the active Team. |
| Cross-Team Lead authorization | PASS | Coordinator ID `17` received HTTP 403 for a Lead in another Team. |
| Logout and re-login | PASS | Sales and Coordinator sessions logged out and re-authenticated successfully. |
| Mobile navigation | PASS | 390×844 checks covered Coordinator dashboard and Lead navigation without visible horizontal overflow. |
| Desktop navigation | PASS | 1440×900 checks covered the tested Sales and Coordinator workflows. |

No consumer names, phone numbers, email addresses, or other consumer PII are recorded in this document.

## Defects found and fixed

### UAT-01 — Coordinator read-only Agenda detail omitted evidence links

- **Observed:** The Coordinator could see the evidence count in the Agenda list, but the read-only detail page did not render the evidence file links.
- **Fix:** Added a scoped evidence section to `resources/views/sales-agendas/create.blade.php`.
- **Regression:** Added Coordinator read-only evidence coverage in `tests/Feature/SalesAgendaAssignmentTest.php`.
- **Retest:** PASS. The Coordinator opened the synthetic Team evidence file successfully.

### UAT-02 — Newly uploaded evidence used the wrong storage disk

- **Observed:** New evidence was stored on the local disk while the generated URL addressed the public disk, resulting in HTTP 403 for the evidence link.
- **Fix:** `SalesAgendaController` now stores uploaded evidence on the `public` disk.
- **Regression:** Updated `tests/Feature/SalesAgendaManagementTest.php` to assert the persisted evidence exists on the public disk.
- **Retest:** PASS. A newly uploaded synthetic image rendered at its evidence URL with no browser error.

## Rehearsal cleanup

- Deleted synthetic Leads `6207` and `6208` and synthetic Agendas `8076`–`8079`, including their synthetic evidence and related rehearsal activity/history rows.
- Removed the temporary uploaded evidence files.
- Restored the original password hashes for rehearsal users `16`, `17`, `18`, and `19`.
- No rehearsal password was committed to the repository.
- Post-cleanup counts returned to 697 Leads, 650 Agendas, and 513 evidence files for the selected Sales scope.

## Verification

- Targeted feature tests: **20 passed, 90 assertions**
- Full PHPUnit suite: **268 passed, 1,167 assertions**
- Laravel Pint: passed
- Vite production build: passed
- Blade view cache: passed
- `git diff --check`: passed

## Cutover preparation items

These are not UAT failures, but they require owner review before production cutover:

1. Preserve and formally sign off the approved historical exceptions: 20 cross-branch Leads, 25 historical Agenda mismatches, and 16 unresolved historical Coordinator-PIC Leads.
2. Resolve or explicitly accept the remaining organization-audit findings: teams without a Coordinator, teams without a project, anonymized users in Teams, and Sales Cases without a Team owner.
3. Keep the retained production export and evidence snapshot available until cutover approval and rollback responsibility are confirmed.
