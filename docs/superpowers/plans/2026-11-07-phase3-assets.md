# Phase 3 (IT assets & CMDB) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Asset register with assignments, transfers + act, inventory by QR, repairs, warranty alerts, write-off approval, exports.

**Architecture:** Same layering as Phases 1–2: FormRequest/validate → services in `app/Services/Assets/*` → models with
`BelongsToOrganization` (+ `Auditable` for reference data); asset history in `asset_events`; frontend feature folder
`src/features/assets`.

**Tech Stack:** Laravel 13 / PHP 8.3, dompdf (act PDF), openspout (export); React 19 + Mantine 9, `qrcode` npm for labels.

**Spec:** `docs/superpowers/specs/2026-11-07-phase3-assets-design.md`

## Global Constraints

- Every endpoint re-checks organization scope; out-of-scope → 404 (route binding via scoped models or `visibleTo`).
- Jobs/commands have no auth user → apply scope explicitly.
- No TODO stubs, mock APIs or hardcoded stats; every button calls a real API.
- Seeders idempotent; demo data only local/development/testing.
- Files private; downloads only via `/files/{uuid}` with a registered `FileAccess` resolver.
- Money: `decimal(15,2)` UZS; dates in Asia/Tashkent for display, UTC storage.
- uz + ru strings for every UI text.

## Review Focus

1. A transfer must never let the sender edit/receive on the receiver side or vice versa; assets appear in the target org only after `received` — Task 5.
2. Inventory scan of an asset from another organization or one not in the snapshot is recorded as "unexpected", never adds it silently; double scans don't double-count — Task 7.
3. Status cannot jump to `written_off`/`in_repair`/back via the generic update; only disposal approval / maintenance do it — Tasks 3, 6.
4. Lookup by inventory number (QR target) returns 404 for out-of-scope assets instead of leaking existence — Task 3.
5. Employee without `assets.view` sees only their own assets (`/assets/mine`), nothing else — Task 4.

---

### Task 1: Foundations
Permissions (config + default roles), migrations for all spec §4 tables, enums `AssetStatus, AssetEventAction,
AssetRelationType, TransferStatus, DisposalStatus, MaintenanceStatus, InventoryStatus`, `NumberSequence` service
(`next(string $key): int`, single-statement upsert like ticket counter), `AssetsSeeder` (spec categories; idempotent,
all envs), morph map. Tests: permissions per role, seeder idempotent, sequence unique under fork concurrency.

### Task 2: Catalog API
`asset-categories`, `manufacturers`, `asset-models` (manufacturer + category), `locations` (per organization, parent
in same org). Read: any `assets.view`/`assets.create`; write: `assets.manage_catalog` (locations: `assets.update` within
org). Delete blocked (409) when referenced. Tests: CRUD, permissions, scope on locations, 409.

### Task 3: Assets core
Model `Asset` (BelongsToOrganization), `AssetService::create/update/delete`, `AssetEventRecorder`, list filters
(`category, status, organization, location, holder, responsible, warranty (expiring|expired), model`), search
(`inventory_number, serial_number, hostname, ip_address, mac_address, name`), sorts, `GET /assets/lookup/{inv}`,
attachments (`asset_attachments` + FileAccess resolver), relations (`asset_relations`, both assets in scope), generic
update cannot set `written_off`/`in_repair` and cannot change status of written-off assets; delete only when no history
beyond `created` (else 409). Tests: CRUD, uniqueness, auto number, IP/MAC validation, filters, lookup 404, attachments
download scope, relations, status rules.

### Task 4: Assignments & employees
`assign(asset, employee, by, note)` (employee must belong to asset org, asset not written_off/lost/in transit), `return`,
status in_use/in_stock transitions, `GET /employees/{id}/assets`, `GET /assets/mine`, dismissal listener → notify
`assets.assign` holders of the org with the list. Tests: assign/return/history, cross-org employee 422, mine, dismissal
notification.

### Task 5: Transfers
`AssetTransferService`: draft (from org in scope, `assets.transfer`), add/remove items (assets of from-org, not
written_off, not in another open transfer), send (generates act PDF `StoredFile`, marks items in transit), receive
(user with `assets.transfer` in to-org; assets → to-org, assignments closed, location cleared, events), cancel (sender,
before received). Visibility: users with access to from or to org. Number `TR-YYYY-NNNNN`. Tests: state machine,
permissions both sides, act download scope, assets moved.

### Task 6: Maintenance, disposal, warranty
Maintenance open → asset `in_repair` (remember previous status), close → restore; optional `ticket_id` (visible ticket).
Disposal request (`assets.update`) → approve/reject (`assets.dispose`); approve → `written_off`, assignment closed.
Command `assets:warranty-check` daily: assets with `warranty_until` within 30 days, notify once per warranty date.
Tests for each.

### Task 7: Inventory sessions
Start (organization subtree, `assets.inventory`): snapshot non-written-off assets. Scan by inventory number: expected →
found (idempotent), not expected → unexpected item (asset in scope or not → store inventory number only when not
visible). Close → counts; items list with filter found/missing/unexpected. Tests per Review Focus 2.

### Task 8: Stats, dashboard, export, documents
`AssetStats` (by status/category/organization, total cost, warranty expiring), dashboard `assets` block, export type
`assets` (ReportDefinition), DocumentLinkTargets type `asset`. Tests.

### Task 9: Backend docs
DATABASE §6, API §9, OPEN_QUESTIONS, ROADMAP.

### Task 10–14: Frontend
10 API/types/list + filters + export + label selection; 11 asset card + form + assign/return + history + files +
relations + maintenance + disposal; 12 transfers; 13 inventory scan screen + labels print + lookup; 14 catalog settings,
report, dashboard tiles, employee card block, my assets, nav. Each with Vitest tests for the main interaction.

### Task 15: Verification
`make check`, browser e2e (desktop + 390px), prod-mode smoke, review, fixes, merge, report.
