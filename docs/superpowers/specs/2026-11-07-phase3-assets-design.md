# Phase 3 (IT assets & CMDB) — Design Spec

**Date:** 2026-11-07 · **Status:** autonomous process (user: build each phase, report after it)
**Source:** `AT_urtg_uz_Claude_Code_Prompt.md` §5.3 (employee assets, dismissal), §5.4 (CMDB), §5.13–5.15, §13 Phase 3
**Builds on:** Phase 1 isolation (`BelongsToOrganization`), Phase 2 building blocks (FileStore/FileAccess, ExportService,
DocumentLinkTargets, ChannelResolver, `visibleTo` pattern).

## 1. Goal

One register of every IT asset across "Urganchtransgaz" and its subsidiaries: card with identity, network and
financial data; who holds it and where it is; full movement history; inter-organization transfers with a
hand-over act; inventory counts by QR scan; repairs, warranty alerts and write-off with approval; exports and
dashboard figures. Ready to show device/IP/hostname relations for monitoring (Phase 5).

## 2. Decisions

| ID | Decision | Rationale |
|---|---|---|
| A1 | Categories seeded from spec §5.4 (codes), editable; manufacturers and models as reference data; locations per organization (building → room, optional parent) | Spec catalogue; admin-editable |
| A2 | Inventory number unique system-wide; entered by accounting or auto-generated `AT-NNNNNN` from `number_sequences` | Spec "unikal"; existing numbers can be kept |
| A3 | Statuses: `in_stock, in_use, in_repair, reserve, written_off, lost` (spec). `in_repair` set/cleared by maintenance records; `written_off` only through an approved disposal | One source of truth for state changes |
| A4 | Assignment = holder employee (optional) + responsible person (moddiy javobgar) + location; history in `asset_assignments` and `asset_events` | Spec card fields + history |
| A5 | Transfer between organizations: `draft → sent → received` (or `cancelled`), number `TR-YYYY-NNNNN`; receiving moves assets to the target org and closes assignments; PDF hand-over act generated on send | Spec "topshirish-qabul dalolatnomasi" |
| A6 | Inventory session per organization subtree: snapshot of expected assets at start; scans mark found / unexpected; close produces found / missing / unexpected report; missing are **not** auto-marked lost | Counting must not silently change records |
| A7 | QR label encodes `https://<host>/assets/lookup/<inventory_number>`; phone camera opens the card; in sessions, scan input accepts keyboard-wedge scanners, typing, and camera via the browser `BarcodeDetector` where available | No native app; works with USB scanners |
| A8 | Write-off: request (assets.update) → approve/reject (assets.dispose); approval sets `written_off`, closes assignment | Spec "hisobdan chiqarish jarayoni" |
| A9 | Dismissed employee: holders' open assignments listed on the employee card and in a notification to org asset managers; return is an explicit action | Spec §5.3 return process |
| A10 | Warranty alert: daily command notifies asset managers of assets whose warranty ends within 30 days (once per asset per warranty date) | Spec "kafolat ogohlantirishlari" |
| A11 | CMDB relations `connected_to / installed_in / part_of / depends_on` with free note (port, slot) | "tarmoq bog'liqliklari" readiness |
| A12 | Files on the card (photos, acts, invoices) via private FileStore; formal documents via Documents link type `asset` | Reuse Phase 2 |
| A13 | Employees see their own assigned assets (`/assets/mine`) without `assets.view` | Spec role 6 |

## 3. Permissions

`assets.view, assets.create, assets.update, assets.delete, assets.assign, assets.transfer, assets.inventory,
assets.dispose, assets.export, assets.manage_catalog`. Defaults: central-it-head → all; central-it-staff and
org-it-responsible → view/create/update/assign/transfer/inventory/export; accountant → view/inventory/export;
org-head and auditor → view/export; employee → none (own assets via `/assets/mine`).

## 4. Data (new tables)

`asset_categories, manufacturers, asset_models, locations, number_sequences, assets, asset_events,
asset_assignments, asset_relations, asset_attachments, asset_maintenances, asset_disposals, asset_transfers,
asset_transfer_items, inventory_sessions, inventory_items`. Columns: `docs/DATABASE.md` §6.

## 5. API (under `/api/v1`)

Catalog CRUD (`/asset-categories`, `/manufacturers`, `/asset-models`, `/locations`); `/assets` CRUD + filters,
`/assets/lookup/{inventory}`, `/assets/mine`, `/assets/{id}/assign|return|status|events|attachments|relations`;
`/assets/{id}/maintenances`; `/asset-disposals` (+ approve/reject); `/asset-transfers` (+ items, send, receive,
cancel, act); `/inventory-sessions` (+ scan, close, items); `/assets/stats`; employee assets
`/employees/{id}/assets`; export type `assets`. Details: `docs/API.md` §9.

## 6. Frontend

Assets list (filters, search by inventory/serial/hostname/IP/MAC, select → print QR labels, export), asset card
(facts, network, holder & assign/return, history, repairs, write-off, relations, files, documents), create/edit,
lookup route, my assets, transfers (list, compose, detail with act), inventory sessions (start, scan screen for
phones, results), disposals approval list, catalog settings, assets report, dashboard tiles, employee card block.

## 7. Testing & acceptance

Backend: isolation (cross-org 404 on every endpoint incl. lookup/files/export), inventory number uniqueness and
generation, status rules (cannot set written_off/in_repair directly), assignment open/close, transfer state
machine (only sender org sends/cancels, only receiver org receives, assets move and assignments close, act PDF),
inventory snapshot/scan/close counts (unexpected and duplicate scans), disposal approve/reject, warranty command
once-only, dismissal notification, export scope, stats correctness. Frontend: list filters/URL, form validation,
card actions per permission, scan screen, labels page, transfer flow. Gates as Phase 2 (`make check`, browser
e2e incl. 390px, prod-mode smoke), independent review, merge.
