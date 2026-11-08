# Phase 4 (Warehouse & requisitions) — Design Spec

**Date:** 2026-11-08 · **Status:** autonomous process (user: build each phase, report after it)
**Source:** `AT_urtg_uz_Claude_Code_Prompt.md` §5.9 (warehouse), §5.10 (requisitions), §5.14–5.15, §13 Phase 4
**Builds on:** Phases 1–3 (org isolation, FileStore/FileAccess, ExportService, ChannelResolver, NumberSequence,
HelpdeskRecipients, asset transfers).

## 1. Goal

Keep the IT service's materials (cartridges, paper, cables, parts…) in warehouses with exact balances: receipts,
issues (to whom / which organization / which device / why), returns, transfers between warehouses, stock counts,
minimum levels, batches and expiry — never negative, every operation journaled. Subsidiaries request resources
through requisitions that pass organization-head and central approval, get fulfilled from stock or the asset
register, and are confirmed on receipt — every step, comment and document in the history.

## 2. Decisions

| ID | Decision | Rationale |
|---|---|---|
| W1 | Catalog: `units`, `material_categories` (seeded from spec §5.9), `materials` (code unique or `MT-NNNNN`, unit, category, `track_batches`, `track_expiry`, `min_quantity`) | Spec catalogue |
| W2 | Warehouses belong to an organization (scope applies); balances per (warehouse, material, batch) with `quantity DECIMAL(18,3)` and DB `CHECK (quantity >= 0)` | Never negative, even with a bug |
| W3 | Every operation is a posted stock document (`receipt, issue, return, transfer, adjustment`) with lines; balances change only inside `StockService` under row locks (`INSERT … ON DUPLICATE KEY UPDATE` for increments, `SELECT … FOR UPDATE` + check for decrements); every line writes `stock_movements` (journal with balance after) | Spec "transaction va locking", "audit izi" |
| W4 | Issues/transfers without a chosen batch allocate FIFO (earliest expiry, then oldest receipt); unit price kept per movement — valuation method pluggable later | Spec FIFO readiness |
| W5 | Documents are posted on save; corrections by return or adjustment (no edits/deletes of posted documents) | Ledger integrity |
| W6 | Stock count per warehouse: snapshot → counted quantities → close posts one adjustment document for differences | Spec "Inventarizatsiya" |
| W7 | Low stock (total per warehouse below `min_quantity`) and expiry within 30 days: dashboard + daily notification once per episode | Spec "minimal qoldiq" alerts |
| R1 | Requisition statuses exactly as spec: `draft, submitted, org_approval, central_review, info_requested, approved, rejected, ready_for_allocation, partially_fulfilled, fulfilled, received, closed` | Spec list |
| R2 | Flow: draft →(submit) `org_approval` (setting `requisitions.head_approval`, default on; skipped when the organization has no active approver) or `submitted`; org head approves → `submitted`, rejects → `rejected`, returns → `draft`. `submitted` is the central queue; a reviewer takes it → `central_review` (reviewer recorded); reviewer may request info → `info_requested`, requester answers → `central_review`. Central head (from `submitted`/`central_review`) approves (may lower quantities) → `approved`, rejects (reason) → `rejected`, returns → `draft`. Fulfiller may mark `ready_for_allocation`; allocations → `partially_fulfilled` / `fulfilled`; fulfiller may finish a partial allocation (comment) → `fulfilled`; requester confirms → `received`; central closes → `closed`. Drafts never seen centrally; a requester cannot approve their own requisition | Spec workflow steps 1–9 |
| R3 | Items: kind `equipment, network, consumable, sim, internet, software, repair, other`; consumables may reference a material, equipment an asset category; approved quantity can be lowered by the central head | Spec resource list |
| R4 | Fulfilment links the resource automatically: material → stock issue document to the requesting organization; asset → if in another organization an asset transfer is created and sent (received together with the requisition), else linked; other kinds → note | Spec step 8 |
| R5 | Allocation from stock: issue document to the requesting organization, or transfer into one of its warehouses when the fulfiller picks one; allocations are never more than the approved remainder (requisition row locked); corrections go through warehouse returns | Spec "ajratish" with ledger integrity |
| W8 | While a stock count is open on a warehouse, stock documents for it are refused (409 `warehouse_counting`) | Snapshot stays true; closing needs every line counted |

## 3. Permissions

`warehouse.view, warehouse.receive, warehouse.issue, warehouse.transfer, warehouse.inventory, warehouse.manage,
warehouse.export`; `requisitions.view, requisitions.create, requisitions.approve_org, requisitions.review,
requisitions.approve, requisitions.fulfill, requisitions.export`.
Defaults: central-it-head → all; central-it-staff → warehouse view/receive/issue/transfer/inventory/export,
requisitions view/review/fulfill; org-it-responsible → warehouse view/receive/issue/transfer/inventory/export,
requisitions view/create; org-head → warehouse view/export, requisitions view/approve_org; accountant → warehouse
view/receive/issue/inventory/export, requisitions view/fulfill; auditor → warehouse view/export, requisitions
view/export.

## 4. Data

`units, material_categories, materials, warehouses, stock_balances, stock_documents, stock_document_lines,
stock_movements, stock_counts, stock_count_lines, stock_alerts, requisitions, requisition_items,
requisition_approvals, requisition_histories, requisition_comments, requisition_attachments,
requisition_fulfillments`. Columns: `docs/DATABASE.md` §7.

## 5. Testing & acceptance

Concurrency: parallel issues never drive a balance below zero (forked workers on real MySQL). FIFO allocation
across batches; transfer keeps batch/expiry; adjustment from counts; low-stock once per episode. Requisition
state machine (every role × status), info request round trip, approvals history, fulfilment from stock and
assets (cross-org → transfer), receipt, visibility (org scope; central sees all in scope), notifications.
Frontend: stock document forms with line editor and live availability, balances/movements, count screen,
requisition create/detail/actions/fulfilment. Gates as before + review + merge.
