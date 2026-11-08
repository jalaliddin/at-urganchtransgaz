# Phase 6 (SIM cards, communication services, payments) — Design Spec

**Date:** 2026-11-08 · **Status:** autonomous process (user: build each phase, report after it)
**Source:** `AT_urtg_uz_Claude_Code_Prompt.md` §5.7, §5.8, §5.1 (SIM and payment tiles), §5.10 (SIM requisitions),
§5.14 (payment due), §5.15 (SIM and cost reports), §8 tables, §13 Phase 6.
**Builds on:** Phase 1 organizations/employees, Phase 2 FileStore/documents/ExportService/ChannelResolver, Phase 4
requisitions (fulfilment), Decimal (money scale 2), AuditLogger (`SecretRevealed`), `open_key` unique-while-open pattern.

## 1. Goal

Know every company SIM card (operator, number, tariff, who holds it, its history) and every internet / communication
service with its contract, monthly cost, invoices and payments — with central control of invoices, due-date reminders
and cost reports per organization, provider and service type. Not an accounting system: an operational control and
planning tool for IT services.

## 2. Decisions

| ID | Decision | Rationale |
|---|---|---|
| M1 | One registry `service_providers` for mobile operators, internet providers and other IT service vendors; `is_mobile_operator` marks those offered for SIM cards. Seeded (all environments, idempotent): Uzmobile, Beeline, Ucell, Mobiuz | Beeline/Uzmobile are both SIM operators and contract parties — one row, no duplicates (§8 "takroriy ma'lumotlar yaratmasdan") |
| M2 | `sim_cards`: operator, phone number stored as `998XXXXXXXXX` (unique among non-cancelled cards via `phone_key`), ICCID 18–22 digits **encrypted at rest** with a keyed hash for uniqueness/search and last 4 digits for display; tariff, account/contract number, monthly fee, holder organization (scope), department, employee, status, allocated/returned dates, note | §5.7 fields; "ICCID maxfiylik va ruxsat nazorati bilan" |
| M3 | Statuses `in_stock, active, blocked, cancelled, returned`. Assign (employee and/or department of the holder organization) from `in_stock`/`returned` → `active`; return → `returned`; block from `in_stock`/`active`/`returned` → `blocked`, unblock → previous status; transfer to another organization (only `in_stock`/`returned`); cancel from any non-cancelled → `cancelled` (terminal, frees the number); replace (new ICCID, same number) on non-cancelled cards. Every change re-reads the card under a row lock | §5.7 status list and "SIM almashtirish" |
| M4 | `sim_assignments` = holder periods (employee/department, from, to, by); `sim_histories` = every event with old/new values | "Xodimga biriktirish va qaytarish tarixi" |
| M5 | SIM requests use the Phase 4 requisition workflow (item kind `sim`): new fulfilment type `sim` picks an available card (`in_stock`/`returned`, visible to the fulfiller, not promised to another open requisition) and transfers it to the requisition's organization at once | Reuses the approved 12-status workflow instead of a second one (§5.10 lists SIM requests) |
| M6 | ICCID is masked for everyone; `sim.manage` may reveal it (audited `SecretRevealed`) | Same pattern as licence keys |
| M7 | `service_contracts` (provider, customer organization, number, signed date, validity, note, terminated date) and `services` (provider, organization, optional contract, type `internet, telephony, mobile, data_center, software, other`, name, address, service identifier, speed/tariff, monthly fee, billing cycle `monthly, quarterly, yearly`, payment due day 1–28, start/end, active) | §5.8 fields |
| M8 | `service_invoices`: one regular row per service and billing period (`period_key` unique) generated daily for the current and the next period of every active service; extra one-off invoices for additional costs. Fields: period, planned amount (fee), invoice number/date/amount, due date (due day in the period's first month, editable), approval, paid amount, file | Planning + "qo'shimcha xarajatlar" |
| M9 | Payment status stored and recomputed on every change and daily: `paid` (invoice entered and fully paid) → `overdue` (due date passed) → `partially_paid` → `due` (invoice entered) → `awaiting_invoice` (period started) → `planned` | §5.8 status list, filterable |
| M10 | Central control: an entered invoice needs approval (`services.approve`) before payments are recorded; payments (`services.pay`, amount, date, document number, file) never exceed the remaining amount (invoice row lock) | "Markaziy AT xizmatining nazorati va tasdiqlash jarayoni" |
| M11 | Daily `services:billing` 08:15: generate periods, refresh statuses, notify once per invoice + due date when due within `services.due_notice_days` (default 5), once when overdue, and once per contract + end date 30 days before a contract ends | §5.14 "SIM/aloqa to'lovi muddati" |
| M12 | Reports: SIM counts by operator × organization × status with monthly fee totals; costs per month of a year (planned, invoiced, paid) grouped by organization, provider or service type. Exports `sim_cards`, `service_invoices`, `service_costs`. Dashboard blocks `sim` and `services` | §5.1, §5.15 |
| M13 | Contracts and SIM cards are document link targets; invoice and payment files via FileAccess resolvers. Employee card lists the SIM cards the employee holds | §5.7 "shartnoma va ilova hujjatlari", §5.3 |

## 3. Permissions

`sim.view, sim.manage, sim.assign, sim.export`; `services.view, services.manage, services.invoice, services.approve,
services.pay, services.export`. Defaults: central-it-head → all; central-it-staff → sim view/manage/assign/export,
services view/manage/invoice/export; org-it-responsible → sim view/assign/export, services view/invoice/export;
org-head, auditor → sim view/export, services view/export; accountant → sim view/export, services
view/invoice/pay/export. Providers are global reference data (read with any `sim.*`/`services.*` view, write
`services.manage` or `sim.manage`).

## 4. Data

`service_providers`, `sim_cards`, `sim_assignments`, `sim_histories`, `service_contracts`, `it_services` (not `services`: clashes with the `App\Services` namespace),
`service_invoices`, `service_payments`, `service_alerts`; `requisition_fulfillments.sim_card_id`. Columns:
`docs/DATABASE.md` §9 (Phase 6).

## 5. Testing & acceptance

Phone normalisation and uniqueness (cancelled numbers reusable), ICCID never plain in lists/exports/audit, reveal
audited. Every SIM transition from every status, row lock, assignment periods and history, employee of another
organization refused, scope 404. Requisition SIM fulfilment (cross-org transfer, unavailable card refused, never
promised twice). Invoice generation idempotent per period, status derivation by dates and payments, approval gate,
no overpayment (including two payments at once), notifications once. Cost report numbers per month and grouping.
Frontend: SIM list/detail/actions, providers, contracts, services, invoices with approve/pay, cost report, requisition
SIM allocation, employee card SIM block, dashboard tiles. Gates as before + review + merge.
