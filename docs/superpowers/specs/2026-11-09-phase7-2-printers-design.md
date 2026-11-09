# Phase 7.2 (Printers and cartridges) — Design Spec

**Date:** 2026-11-09 · **Status:** autonomous process (user: build each phase, report after it)
**Source:** `AT_urtg_uz_Claude_Code_Prompt.md` §5.12 (printers and consumables), §5.9 (warehouse), §5.15 reports
("Kartrij va printer sarfi"), §8 tables (`printers`, `printer_models`, `cartridge_models`,
`printer_cartridge_compatibilities`, `cartridge_movements`), §13 Phase 7.
**Builds on:** Phase 3 assets (category `printer`, asset models, locations, holders, maintenances), Phase 4 warehouse
(`materials`, `StockService`, FIFO lots, issue/return documents, low-stock alerts), ExportService, dashboard.

## 1. Goal

IT staff keep a printer registry tied to the asset register, know which cartridges fit which printer model, replace
cartridges straight from the warehouse (the stock goes down by itself), record page counters, and see per printer, per
organization and per cartridge model how many cartridges were used, what they cost and how many pages were printed.

## 2. Decisions

| ID | Decision | Rationale |
|---|---|---|
| P1 | A printer is an asset in the seeded category `printer` (code is not editable). The registry lists those assets in the user's scope; printer-only data lives in a 1:1 `printers` profile (connection type `usb/network/wifi/shared`, colour flag, note), created on first write. Organization, location/room, holder, model, serial and IP stay on the asset | §5.12 fields already exist on assets; no duplicate register (§8 `assets ||--o| printers`) |
| P2 | Printer models are the asset models of the `printer` category (no separate `printer_models` table) | One model catalog |
| P3 | Cartridge model: name, part number (unique when given), kind (`toner, ink, drum, ribbon, other`), colour (`black, cyan, magenta, yellow, tricolor, other`), page yield, active flag, and exactly one warehouse material (unique). Picking no material creates one with the cartridge (category `cartridge`, unit `dona`). Compatibility = set of printer models | §5.12 "mos kartrij modeli" + "ombor moduli bilan integratsiya": stock, receipts and issues of cartridges are the material's |
| P4 | A replacement records printer, cartridge model, date (not in the future), source and optional page counter. Source `warehouse` posts a stock issue of 1 unit through `StockService` (FIFO, device = printer, recipient = printer's organization and holder) in the same transaction; source `other` (already handed to the organization, refilled by a contractor, bought directly) moves no stock and has no cost | Stock never drifts from history; the branch that received cartridges earlier can still record installs |
| P5 | The cartridge must be compatible with the printer's model when that model has a compatibility list; otherwise any active cartridge model is accepted | Printers without a model or catalog data must still be serviceable |
| P6 | A replacement is never deleted; cancelling it (reason required) posts a return of the issue to the same warehouse at the issue cost and marks it cancelled. Cancelled replacements stay in history, struck through, and are left out of every count | Audit trail; stock consistent |
| P7 | Counter readings: date, total pages, reset flag, source (`manual`, `replacement`), note. Without the reset flag a reading must be ≥ the previous reading and ≤ the next one (by date, then entry order). Pages of a reading = total − previous total (= total after a reset; the first reading is the baseline). Pages of a period = sum of pages of the readings dated in it. Readings can be deleted, not edited | §5.12 counter + page statistics "mavjud ma'lumot asosida" |
| P8 | Replacements and readings store the printer's organization at that moment; reports and scope use it | History stays with the organization that used it after a transfer |
| P9 | Analysis `printer-report`: period (default this year) and grouping by organization, cartridge model or printer: replacements, cost (warehouse issues), pages (not for cartridge grouping), cost per page; plus a 12-month breakdown | §5.12 "tashkilotlar kesimida tahlil", §5.15 |
| P10 | Per replacement the history shows pages since the previous replacement of the same cartridge model in that printer (when both have counters) next to the nominal yield | Shows real yield vs nominal |
| P11 | Repair history on the printer page = the asset's maintenance records (Phase 3, linked tickets included); no new repair table | §5.12 "nosozlik va ta'mir tarixi" exists |
| P12 | An asset with replacements or counter readings cannot be deleted (409, like other operational history) | Keeps history |
| P13 | Refills are not tracked unit by unit: a refilled cartridge comes back as an ordinary warehouse receipt (supplier = refill contractor, price = refill price) | Open question with a safe default |

## 3. Permissions

`printers.view, printers.service, printers.manage, printers.export`. `service` = printer profile, replacements, counter
readings (a warehouse-source replacement and its cancel also need `warehouse.issue`); `manage` = cartridge catalog and
compatibility. Defaults: central-it-head → all; central-it-staff → view/service/export; org-it-responsible →
view/service/export; org-head, accountant, auditor → view/export; employee → none.

## 4. Data

`printers` (asset_id PK), `cartridge_models`, `printer_cartridge_compatibilities`, `cartridge_replacements`
(the `cartridge_movements` of §8 — stock movements themselves stay in `stock_movements`), `printer_counters`.
Columns: `docs/DATABASE.md` §11 (Phase 7.2).

## 5. Testing & acceptance

Scope 404 (printer, replacement, counter of another organization; a non-printer asset); permissions per role;
catalog rules (unique material and part number, auto-created material, compatibility only with printer models, delete
only when unused); replacement from a warehouse lowers the lot FIFO and journals the movement, fails cleanly on no stock
or a counting warehouse, needs `warehouse.issue`; compatibility rule; future date rejected; cancel returns the stock once
(also with two cancels at once); counter monotonicity incl. back-dated readings and resets; page maths; report numbers
per grouping and scope; exports; dashboard tile; asset delete blocked. Frontend: registry, printer page (replace,
counter, cancel, profile), cartridge catalog, replacement journal, analysis page, asset page link, dashboard tile.
Gates as before + review + merge.
