# Phase 6 (SIM cards, services, payments) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** SIM card registry with lifecycle and history, provider/contract/service registry, billing periods with
invoices, central approval and payments, due-date reminders, cost reports.

**Architecture:** Same layering as Phases 1–5: FormRequest → services in `app/Services/Sim/*` and
`app/Services/Telecom/*` → models with `BelongsToOrganization`. Status changes only in services, inside one transaction
under a row lock. Frontend feature folders `src/features/sim` and `src/features/services`.

**Tech Stack:** Laravel 13 / PHP 8.3 (encrypted cast, bcmath money), React 19 + Mantine 9.

**Spec:** `docs/superpowers/specs/2026-11-08-phase6-sim-services-design.md`

## Global Constraints

- Every endpoint re-checks organization scope; out-of-scope → 404 (scoped models / `visibleTo`).
- Jobs/commands have no auth user → apply scope explicitly.
- No TODO stubs, mock APIs or hardcoded stats; every button calls a real API.
- Money `DECIMAL(15,2)` UZS compared with bcmath strings (`Decimal::of($v, 2)`), never floats.
- Status changes re-read the row with `lockForUpdate()` inside the transaction; counts/sums that guard a limit are
  locking reads (REPEATABLE READ snapshot lesson from Phase 5).
- ICCID encrypted at rest, `$hidden`, `auditExclude`, masked in lists/exports/history.
- Seeders idempotent; demo data only local/development/testing (operators are reference data, all envs).
- uz + ru strings for every UI text.

## Review Focus

1. Two payments recorded at once never exceed the invoice amount — Task 5 (two-connection test).
2. A SIM card is never allocated to two requisitions/organizations at once and a phone number is unique among
   non-cancelled cards — Task 3.
3. Payment status is derived by one function, the same in lists, dashboard, reports and reminders — Task 5, Task 6.
4. ICCID never appears plain in a list, export, audit log or history payload — Task 2.
5. SIM transfer / service / invoice actions never reach organizations outside the user's scope — Tasks 2–5.

---

### Task 1: Foundations
Permissions + defaults (spec §3), migrations (all spec §4 tables, `requisition_fulfillments.sim_card_id`), enums
`SimStatus`, `SimEvent`, `ServiceType`, `BillingCycle`, `InvoiceStatus`, `InvoiceKind`, `FulfillmentType::Sim`,
models + morph map, `TelecomSeeder` (operators, idempotent, all envs), setting `services.due_notice_days` (5),
`config/services_billing.php` (`contract_notice_days` 30). Tests: permissions per role, seeder idempotent.

### Task 2: Providers + SIM registry
Providers CRUD (`/service-providers`, 409 when used). SIM CRUD `/sim-cards` (filters operator, status, organization,
search by phone or full ICCID via hash), phone normalisation, ICCID encrypt/hash/last4, `POST /sim-cards/{id}/reveal`
(audited), `GET /sim-cards/{id}` with assignments and history. Tests: normalisation, uniqueness, masking, scope.

### Task 3: SIM lifecycle + requisitions
`SimService`: assign, return, block, unblock, transfer, cancel, replace (row lock, assignments, history). Employee
SIM list `/employees/{id}/sim-cards`. Requisition fulfilment type `sim` (available card, not promised, transfer to the
requisition org). Tests: transition matrix, employee of another org refused, double allocation refused.

### Task 4: Contracts + services
`/service-contracts`, `/services` CRUD with scope, document link targets `service_contract`, `sim_card`. Tests.

### Task 5: Invoices + payments
`BillingService::generate(today)` (current + next period per active service, idempotent), `InvoiceStatus::derive`,
invoice entry (`PUT /service-invoices/{id}`), extra invoices, approve, payments (locking sum, no overpayment), files,
delete rules. Tests incl. two-connection payment race.

### Task 6: Command, notifications, dashboard, reports, exports, docs
`services:billing` daily 08:15; `ServicePaymentDueNotification`, `ContractExpiringNotification`; dashboard `sim`,
`services`; `/sim-report`, `/service-costs`; exports `sim_cards`, `service_invoices`, `service_costs`. Docs
(DATABASE §9, API §12, OPEN_QUESTIONS, ROADMAP).

### Task 7–8: Frontend
7 SIM list/form/detail/actions/report + requisition SIM allocation + employee block; 8 providers, contracts, services,
invoices (approve/pay), cost report, dashboard tiles, navigation, notification types. Vitest for the main flows.

### Task 9: Verification
`make check`, browser e2e (desktop + 390px), review, fixes, merge, report.
