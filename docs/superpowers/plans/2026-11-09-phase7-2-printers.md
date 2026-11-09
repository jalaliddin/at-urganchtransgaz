# Phase 7.2 (Printers and cartridges) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Printer registry on top of the asset register, cartridge catalog tied to warehouse materials, cartridge
replacements that issue stock, page counters, page and consumption analysis.

**Architecture:** FormRequest/validation → `app/Services/Printers/*` → models. A printer is an `Asset` whose category
code is `printer`; `/printers/{printer}` binds such an asset in the user's scope (else 404). Replacements call
`StockService::post` (issue / return) inside their own transaction, after locking the printer profile row; counter
writes lock the same row. Frontend `src/features/printers`.

**Tech Stack:** Laravel 13 / PHP 8.3, React 19 + Mantine 9, TanStack Query.

**Spec:** `docs/superpowers/specs/2026-11-09-phase7-2-printers-design.md`

## Global Constraints

- Every endpoint re-checks organization scope; out-of-scope → 404. Jobs/exports apply scope explicitly (`visibleTo`).
- Reads that guard a write (previous/next counter, cancelled flag) are locking reads inside the transaction.
- Stock changes only through `StockService`; never write `stock_*` tables directly.
- Never run backend tests while `make check` runs (shared test DB).
- No TODO stubs, mock APIs or hardcoded stats; uz + ru strings for every UI text.

## Review Focus

1. Two cancels of one replacement at once return the cartridge to stock only once — Task 4 (two-connection test).
2. A replacement whose stock issue fails (no stock, counting warehouse, inactive warehouse) leaves no replacement and no
   counter row — Task 4.
3. A back-dated reading that is above a later reading is rejected; two readings written at once cannot both break the
   order — Task 3.
4. A warehouse, printer, replacement or reading outside the user's organizations is 404/422, and a non-printer asset is
   404 on `/printers/{id}` — Tasks 3–4.
5. Reports leave out cancelled replacements, count pages after a reset correctly and cut periods by Tashkent dates —
   Task 5.

---

### Task 1: Foundations
Permissions `printers.{view,service,manage,export}` + role defaults (spec §3); migration (DATABASE §11); enums
`PrinterConnection`, `CartridgeKind`, `CartridgeColor`, `ReplacementSource`, `CounterSource`; models `Printer`,
`CartridgeModel`, `CartridgeReplacement` (BelongsToOrganization), `PrinterCounter` (BelongsToOrganization);
`AssetCategory::PRINTER`; asset delete guard (P12). Tests: permissions per role, asset with history not deletable.

### Task 2: Cartridge catalog
`/cartridge-models` index (filters: search, active, printer model, kind) / store / update / destroy (409 when used) with
`printer_model_ids` (only printer-category asset models) and `material_id` (unique) or auto-created material;
`/printer-models`. Tests: rules above, permissions.

### Task 3: Registry, profile, counters
`/printers` index (filters organization, connection, model, status, cartridge, search) + detail (profile, compatible
cartridges with stock in visible warehouses, recent replacements, readings, 12-month pages, maintenances) + `PUT` profile;
`PrinterCounters::record/delete` (P7, profile row lock), `/printers/{p}/counters`, `/printer-counters/{id}`;
`PrinterPages` (deltas, monthly). Tests: scope, binding 404, monotonic incl. back-dated and reset, page maths, race.

### Task 4: Replacements
`CartridgeReplacements::replace/cancel` (P4–P6, P10), `POST /printers/{p}/replacements`, `GET /cartridge-replacements`
(journal filters: organization, printer, cartridge, source, dates, cancelled), `POST /cartridge-replacements/{id}/cancel`.
Tests: FIFO issue + journal, other source, compatibility, future date, permissions, counting warehouse rollback,
cancel return once (two connections), counter created with the replacement.

### Task 5: Reports, dashboard, exports, docs
`PrinterReports::report/widget`, `/printer-report`, dashboard block `printers` (printers, replacements this month,
cartridges in stock, cartridge materials below minimum), exports `printers`, `cartridge_replacements`,
`printer_consumption`; docs (DATABASE §11, API §14, OPEN_QUESTIONS Q60+, ROADMAP). Tests.

### Task 6–7: Frontend
6 types/api/queries, registry page, printer page (profile modal, replace modal with source/warehouse/compatible
cartridges, counter modal, cancel), asset page link, navigation. 7 cartridge catalog page, replacement journal, analysis
page, dashboard tile, locales uz/ru, permission labels. Vitest for the main flows.

### Task 8: Verification
`make check`, browser e2e (desktop + 390px), review, fixes, merge, report.
