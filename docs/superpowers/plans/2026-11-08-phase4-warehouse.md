# Phase 4 (Warehouse & requisitions) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Material warehouses with exact, never-negative balances (receipt/issue/return/transfer/count, batches, expiry,
minimum levels, journal) and requisitions with org-head + central approval, fulfilment from stock/assets and receipt.

**Architecture:** Same layering as Phases 1–3: FormRequest → services in `app/Services/Warehouse/*` and
`app/Services/Requisitions/*` → models with `BelongsToOrganization`. Balances change only in `StockService` inside one
transaction under row locks; every lot change writes `stock_movements`. Frontend feature folders
`src/features/warehouse` and `src/features/requisitions`.

**Tech Stack:** Laravel 13 / PHP 8.3 (bcmath for decimal math), MySQL 8 CHECK constraint; React 19 + Mantine 9.

**Spec:** `docs/superpowers/specs/2026-11-08-phase4-warehouse-design.md`

## Global Constraints

- Every endpoint re-checks organization scope; out-of-scope → 404 (scoped models / `visibleTo`).
- Jobs/commands have no auth user → apply scope explicitly.
- No TODO stubs, mock APIs or hardcoded stats; every button calls a real API.
- Seeders idempotent; demo data only local/development/testing.
- Files private; downloads only via `/files/{uuid}` with a registered `FileAccess` resolver.
- Quantities `DECIMAL(18,3)` compared with bcmath strings (never floats); money `DECIMAL(15,2)` UZS.
- Status changes re-read the row with `lockForUpdate()` inside the transaction and re-check there.
- uz + ru strings for every UI text.

## Review Focus

1. Two parallel issues of the same lot never leave a negative balance; multi-lot operations lock lots in ascending id
   order (no deadlock) — Task 3 (fork test).
2. A user cannot post into/out of a warehouse outside their organizations, nor transfer to such a warehouse — Task 4.
3. Requisition actions per stage: requester cannot approve their own; org head of org A cannot act on org B; central staff
   (review) cannot approve; drafts invisible centrally — Task 7.
4. Allocation never exceeds the approved remainder, also when two fulfillers allocate at once — Task 8.
5. Stock documents on a warehouse with an open count are refused (409) — Task 5.

---

### Task 1: Foundations
Permissions `warehouse.{view,receive,issue,transfer,inventory,manage,export}` and
`requisitions.{view,create,approve_org,review,approve,fulfill,export}` + defaults (spec §3). Migrations for all spec §4
tables (`stock_balances` UNIQUE `warehouse_id, material_id, batch` with `batch` NOT NULL default `''`, CHECK
`quantity >= 0`; `stock_alerts.open_key` nullable UNIQUE for one open alert per episode). Enums `StockDocumentType`,
`StockCountStatus`, `StockAlertType`, `RequisitionStatus`, `RequisitionItemKind`, `RequisitionAction`,
`RequisitionDecision`, `FulfillmentType`. Models + morph map. `WarehouseSeeder` (units, spec categories; idempotent, all
envs). Setting `requisitions.head_approval` (boolean, default true). `config/warehouse.php` (`expiry_notice_days` 30).
Tests: permissions per role, seeder idempotent, DB rejects a negative balance.

### Task 2: Catalog API
`units`, `material-categories`, `materials` (code unique, `MT-NNNNN` when empty; unit, category, `track_batches`,
`track_expiry`, `min_quantity`), `warehouses` (organization, name unique per org, responsible user, location). Read:
`warehouse.view` or `requisitions.create`; write `warehouse.manage` (warehouses only within accessible orgs). Delete 409
when referenced. Tests: CRUD, permissions, scope, 409.

### Task 3: StockService core
`post(StockDocumentType, array $header, array $lines, User): StockDocument` for receipt (lots by batch, cost averaged
inside a lot), issue (explicit batch or FIFO: earliest expiry, then oldest lot), return (into a lot, optional link to the
issue it reverses — not more than issued minus returned), transfer (FIFO from source, same batch/expiry/cost into
target), adjustment (signed lines). Locks lots ascending id; decrement only after `quantity >= ?` check → 422
`insufficient_stock` with `{material, available, requested}`; increments by upsert. Number `<RC|IS|RT|TF|AD>-YYYY-NNNNN`.
Journal row per lot change with `balance_after`. Batch required when `track_batches`; expiry required when
`track_expiry`. Tests: each type, FIFO order, batch rules, cost, journal, negative refusal, fork concurrency.

### Task 4: Stock API
`POST /stock-documents` (type-specific FormRequest; permission per type: receive/return/adjust → `warehouse.receive`,
issue → `warehouse.issue`, transfer → `warehouse.transfer`), list (filters `type, warehouse, material, date_from,
date_to, organization`), show (lines, movements, links), attachment upload (`file_id`, FileAccess resolver),
`GET /stock-balances` (filters `warehouse, material, category, low, expiring, organization`; totals per material),
`GET /stock-availability?warehouse_id&material_id` (lots), `GET /stock-movements`. Issue recipient: employee of the
warehouse org or of `recipient_organization_id` (in scope); asset (device) must be visible. Tests: permissions, scope,
filters, attachments.

### Task 5: Stock counts
Start (warehouse in scope, `warehouse.inventory`; one open count per warehouse) → snapshot lots with quantity > 0;
`PATCH` counted quantities (bulk), add line (material + batch not in snapshot); close (all lines counted) → one
adjustment document with the differences, count `closed`; cancel. While open, Task 3 refuses documents for that
warehouse (409 `warehouse_counting`). Tests: snapshot, freeze, close adjustment, missing count 422, cancel.

### Task 6: Alerts, dashboard, exports
`StockAlerts::sync(warehouseId, materialId)` after every posted document (after commit): opens a `low_stock` alert and
notifies `warehouse.receive` holders of the org once per episode, resolves when back to minimum. Command
`warehouse:stock-check` daily 08:00: resync low stock + `expiring` alerts for lots expiring within
`expiry_notice_days` (once per lot + expiry date). Dashboard block `warehouse` (low stock count, expiring lots, documents
this month). Exports `stock_balances`, `stock_movements`. Tests.

### Task 7: Requisitions workflow
Model + `visibleTo` (drafts: requester or `requisitions.create` in org; others: `requisitions.view`), number
`RQ-YYYY-NNNNN`, draft CRUD with items (kind, material or asset category, name, quantity, unit), delete draft,
comments, attachments (FileAccess), `RequisitionWorkflow` actions: submit, org approve/reject/return, take, request info,
provide info, approve (approved quantities), reject (reason), return; approvals + history rows; notifications
(`RequisitionNotification`) to the next actor and the requester. Tests: full matrix, Review Focus 3, head approval
skip, history.

### Task 8: Fulfilment, receipt, close, export
`RequisitionFulfilment::allocate(item, type stock|asset|note, …)`: stock → Task 3 issue (to requisition org) or
transfer (into its warehouse); asset → same org: link; other org: asset transfer created + sent; note → text.
Remaining = approved − fulfilled under requisition row lock. Ready-for-allocation, finish partial, receive (sets
`received`, receives linked asset transfers via `AssetTransferService::receiveFor`), close. Export `requisitions`,
dashboard block `requisitions` (awaiting my action). Tests incl. concurrent allocation.

### Task 9: Backend docs
DATABASE §7, API §10, OPEN_QUESTIONS, ROADMAP.

### Task 10–14: Frontend
10 warehouse API/types + balances page + movements journal + catalog settings (units, categories, materials,
warehouses); 11 stock documents list + document drawer (line editor with live availability per lot) + detail/print;
12 count screen; 13 requisitions list (tabs) + form + detail (items, timeline, comments, files, actions per role);
14 allocation dialog + receipt + dashboard tiles + navigation + exports. Vitest tests for the main interactions.

### Task 15: Verification
`make check`, browser e2e (desktop + 390px), review, fixes, merge, report.
