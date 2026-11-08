# Phase 5 (Software, licenses, antivirus, agents) — Design Spec

**Date:** 2026-11-08 · **Status:** autonomous process (user: build each phase, report after it)
**Source:** `AT_urtg_uz_Claude_Code_Prompt.md` §5.5, §5.1 (antivirus tile), §5.14 (licence expiry), §5.15 (compliance report),
§13 Phase 5 · **Scope change (user, 2026-11-08):** ICMP network monitoring (§5.6) is **not needed** and is dropped.
**Builds on:** Phase 3 assets (devices), FileStore, ExportService, ChannelResolver, HelpdeskRecipients, Decimal.

## 1. Goal

Know which software and which antivirus every computer has, whether it is licensed and up to date, and what is missing
against the required-software policy — per device and per organization. Data comes from manual entry, CSV import or,
later, an agent / AD / antivirus console through a token-protected API that can only write its own device.

## 2. Decisions

| ID | Decision | Rationale |
|---|---|---|
| S1 | Catalog `software` (name unique, vendor, category, licence type, `is_antivirus`) — antivirus/EDR products are catalog rows | Spec "Antivirus/EDR mahsuloti" |
| S2 | `software_installations` per device: catalog link **or** an unrecognised raw name (agent/CSV), version, source (manual/csv/agent), licence, `last_seen_at`; unique per device + normalised name. Adding an unrecognised name to the catalog links every row with that name | One table instead of a separate raw `software_inventory`; nothing an agent reports is lost |
| S3 | Licences: software, owner organization, seats, validity, cost, key **encrypted at rest**, shown masked; reveal is audited. Used seats = linked installations; over-assignment refused | Spec seats used/free; secrets |
| S4 | Antivirus status per device (product, status, definitions date, last scan, reported at, source). Effective status: `installed_updated`, `outdated` (definitions older than `software.av_outdated_days`, default 7), `not_installed`, `unknown` (no report or report older than 30 days) | Spec status list |
| S5 | Required-software policies: software × (organization subtree or all) × (asset category or all computers). Compliance = devices in scope that miss a required title | Spec "o'rnatilishi kerak bo'lgan dasturlar siyosati" |
| S6 | Agents: one per device; tokens random 40 chars, stored as SHA-256 hash with a visible prefix, shown once; revocable. `POST /api/v1/agent/checkin` (Bearer token, throttled) replaces that device's agent-sourced installations and antivirus status; every check-in logged | Spec "agent tokenlari maxfiy, qurilma faqat o'ziga tegishli" |
| S7 | CSV import (software list or antivirus status) by inventory number, synchronous, ≤ 5000 rows, per-row results; rows for devices outside the user's scope are rejected | Spec "qo'lda kiritish va CSV import" |
| S8 | Licence expiry alert 30 days ahead, daily, once per licence + date, to `software.manage` holders of the owner organization | §5.14 |
| S9 | ICMP monitoring (§5.6) dropped by the user; no worker, no `NET_RAW` | User decision |

## 3. Permissions

`software.view, software.install, software.manage, software.agents, software.export`. Defaults: central-it-head → all;
central-it-staff → view/install/agents/export; org-it-responsible → view/install/export; org-head, auditor → view/export;
accountant → view.

## 4. Data

`software`, `software_licenses`, `software_installations`, `software_policies`, `antivirus_statuses`, `device_agents`,
`agent_tokens`, `agent_checkins`, `license_alerts`. Columns: `docs/DATABASE.md` §8 (Phase 5).

## 5. Testing & acceptance

Token: hash only stored, wrong/revoked token 401, a token writes only its device, rate limit. Check-in replaces agent
rows, keeps manual ones, matches catalog case-insensitively. Seats cannot be over-assigned. Effective antivirus status
by age. Compliance numbers per organization. CSV: scope, unknown inventory numbers, malformed rows. Licence alert
once. Scope 404 everywhere. Frontend: software catalog, licences (masked key, reveal), asset card "Dasturlar" tab
(installations, antivirus, agent token shown once), compliance report, CSV import results. Gates as before.
