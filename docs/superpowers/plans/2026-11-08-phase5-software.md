# Phase 5 (Software, licenses, antivirus, agents) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Software catalog, licences with seats, per-device installations and antivirus status, required-software
compliance, CSV import and a token-protected agent check-in API.

**Architecture:** Same layering as Phases 1–4: FormRequest → services in `app/Services/Software/*` → models. Device data
hangs off Phase 3 `assets` (scope = asset organization). Agent API is a separate route group with its own token guard
middleware (not Sanctum). Frontend feature folder `src/features/software` plus a tab on the asset card.

**Tech Stack:** Laravel 13 / PHP 8.3 (encrypted cast for licence keys), React 19 + Mantine 9.

**Spec:** `docs/superpowers/specs/2026-11-08-phase5-software-design.md`

## Global Constraints

- Every endpoint re-checks organization scope; out-of-scope → 404 (assets via `Asset::visibleTo`).
- Jobs/commands have no auth user → apply scope explicitly.
- No TODO stubs, mock APIs or hardcoded stats; every button calls a real API.
- Secrets: licence keys encrypted at rest; agent tokens stored only as SHA-256 hashes, plain value shown once.
- uz + ru strings for every UI text. ICMP monitoring is out of scope (user decision).

## Review Focus

1. An agent token can only write the device it belongs to; revoked/unknown tokens get 401; the plain token never
   appears in any later response or log — Task 4.
2. Licence seats are never over-assigned, also when two installations are linked at once (licence row lock) — Task 2.
3. CSV import never touches devices outside the importing user's organizations — Task 5.
4. Effective antivirus status is computed from dates (stale report → unknown, old definitions → outdated) the same
   way in the device list, compliance report and dashboard — Task 3, Task 6.
5. Agent check-in removes agent-sourced rows the agent no longer reports but keeps manual rows — Task 4.

---

### Task 1: Foundations + catalog
Permissions + defaults, migrations (all spec §4 tables; `assets.software_inventoried_at`), models + morph map, setting
`software.av_outdated_days` (7), catalog API `GET/POST/PUT/DELETE /software` (search, category, antivirus filter;
delete 409 when used), `normalized_name` helper. Tests: permissions per role, catalog CRUD, uniqueness, 409.

### Task 2: Installations + licences
`SoftwareInstallations` service: add/update/remove per device (manual), link licence (licence row lock, seats check,
same software), unrecognised names → `POST /software/{id}/adopt` links rows with that name. Licences API with
encrypted key (masked in lists, `POST /software-licenses/{id}/reveal` audited), seats used/free. Device endpoints
`/assets/{id}/software`. Tests: seats, scope, adopt, key never plain in list.

### Task 3: Antivirus + policies + compliance
`antivirus_statuses` upsert per device (manual), `AntivirusStatus::effective()`; policies CRUD
(`software.manage`); `ComplianceService` per organization (devices, AV breakdown, missing required) + device-level
missing list. Tests: effective status by dates, compliance numbers, scope.

### Task 4: Agents
Agents per device (`software.agents`), token issue (plain once) / revoke, `AgentToken` middleware, check-in endpoint
with payload validation, replace agent rows, AV status, `software_inventoried_at`, check-in log, throttle. Tests:
Review Focus 1 and 5.

### Task 5: CSV import
`POST /software-imports` (`type: installations|antivirus`, file) → per-row report. Tests: Review Focus 3, malformed rows.

### Task 6: Alerts, dashboard, exports, docs
`software:license-check` daily (S8), dashboard block `software` (devices with AV ok / not protected, licences
expiring), exports `software_compliance`, `software_installations`, `software_licenses`. Docs (DATABASE §8, API §11,
OPEN_QUESTIONS, ROADMAP with ICMP dropped).

### Task 7–8: Frontend
7 software catalog + licences + policies + compliance report + import page + navigation; 8 asset card "Dasturlar"
tab (installations, antivirus, agent + token once), dashboard tiles, notification types. Vitest for the main flows.

### Task 9: Verification
`make check`, browser e2e, review, fixes, merge, report.
