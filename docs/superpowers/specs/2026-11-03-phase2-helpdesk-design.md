# Phase 2 (Helpdesk) — Design Spec

**Date:** 2026-11-03 · **Status:** approved process — autonomous (user chose "build each phase, report after it")
**Source:** `AT_urtg_uz_Claude_Code_Prompt.md` §5.2 (Helpdesk), §5.13 (documents base), §5.14 (notifications),
§5.15 (reports/export), §13 Phase 2 · **Builds on:** Phase 1 (`docs/ARCHITECTURE.md`, isolation via `BelongsToOrganization`)

## 1. Goal

Employees and subsidiaries file IT requests (also from a phone); IT staff triage, assign, work,
resolve and close them under business-hours SLAs with a full history; managers see statistics and
export lists to Excel/PDF. Adds the reusable pieces later phases need: private file storage,
documents with versions and links, queued exports, business calendar, mail-capable notifications.

## 2. Decisions

| ID | Decision | Rationale |
|---|---|---|
| H1 | SLA counted in **business minutes**: Mon–Fri 09:00–18:00, lunch 13:00–14:00, Asia/Tashkent, holidays list — all in settings (user-approved) | Evening/weekend tickets are not "late" |
| H2 | Statuses: `new, in_review, assigned, in_progress, waiting_user, resolved, closed, rejected` with an explicit transition map (§4) | Spec list; one place to reason about workflow |
| H3 | Number `HD-YYYY-NNNNNN`, yearly counter row locked `FOR UPDATE` | Unique, readable, no gaps under concurrency |
| H4 | Visibility: org scope (global scope) **and** either `helpdesk.view` or being the requester/creator | Employees see only their own tickets |
| H5 | `waiting_user` pauses the resolution clock (due date shifted by the business minutes spent waiting) | Customer delay is not IT delay |
| H6 | First response = first handler action (public comment, assignment or status change by a handler) | Measurable, not gameable by requester |
| H7 | Generic `stored_files` (private disk, UUID path, sha256) used by ticket attachments and document versions; downloads only through policy-checked API | §5.13, §10 |
| H8 | Uploads: ≤ `files.max_upload_mb` (10), extension allowlist + MIME sniffing, executable/script/markup types blocked | §5.13 |
| H9 | Exports: `report_exports` row + queued job that re-applies the **requesting user's** scope explicitly (no auth in jobs); XLSX via openspout, PDF via dompdf (DejaVu Sans: Latin-ext + Cyrillic) | §5.15 "queue orqali", Phase 1 scope rule for jobs |
| H10 | Notifications: database always; mail when `notifications.mail_enabled` and the user has an email; channel chosen in one resolver so Telegram/SMS plug in later | §5.14 |
| H11 | Auto-close resolved tickets after `helpdesk.auto_close_days` (3) business days; requester may confirm/close earlier or reopen within `helpdesk.reopen_days` (7) | Common ITSM practice; configurable |
| H12 | Documents module base (types, number, date, org, versions, polymorphic links) with UI; assets etc. link to it in later phases | Roadmap Phase 2 |

## 3. Data model (new tables)

`stored_files`, `ticket_categories`, `ticket_sla_policies` (priority × optional category →
response/resolution business minutes), `ticket_counters`, `helpdesk_tickets`, `ticket_comments`
(`is_internal`), `ticket_attachments`, `ticket_histories`, `ticket_links`, `documents`,
`document_versions`, `document_links` (morph), `report_exports`. Columns: `docs/DATABASE.md` §5 (Phase 2).
Tickets snapshot `sla_response_minutes` / `sla_resolution_minutes` so later policy edits do not
rewrite history.

## 4. Workflow

| From | To (handler: `helpdesk.process`) | Requester |
|---|---|---|
| new | in_review, assigned, in_progress, rejected | edit fields |
| in_review | assigned, in_progress, rejected | — |
| assigned | in_progress, waiting_user, resolved | — |
| in_progress | waiting_user, resolved | — |
| waiting_user | in_progress, resolved | reply (comment) → back to in_progress |
| resolved | in_progress (reopen), closed | close (confirm), reopen ≤ 7 days, rate |
| closed | in_progress (reopen ≤ 7 days) | reopen ≤ 7 days, rate |
| rejected | — | — |

`resolved` needs a resolution text; `rejected` needs a reason. Assignment (`helpdesk.assign`, or
"take" by a handler) sets `assigned` when the ticket is new/in_review. Assignee must hold
`helpdesk.process` and access to the ticket's organization.

## 5. Permissions (added to the registry; RbacSeeder grants them to default roles on next seed)

`helpdesk.view, helpdesk.create, helpdesk.process, helpdesk.assign, helpdesk.export, helpdesk.manage`,
`documents.view, documents.manage`. Defaults: employee → create; org-head → create/view/export;
org-it-responsible → create/view/process/assign/export + documents; central-it-staff →
create/view/process/export + documents; central-it-head → all; accountant → create + documents;
auditor → view/export + documents.view.

## 6. API (all under `/api/v1`)

Categories & SLA (`/ticket-categories`, `/ticket-sla-policies`), tickets (`/helpdesk/tickets` CRUD-ish,
`/assign`, `/take`, `/transition`, `/comments`, `/attachments`, `/links`, `/rate`, `/time`, `/history`),
`/helpdesk/assignees`, `/helpdesk/stats`, `/files/{uuid}` download, `/documents` (+ versions, links),
`/exports` (create, list mine, download). Details: `docs/API.md` §8.

## 7. Frontend

Helpdesk list (tabs: mine / assigned to me / all in scope; filters; SLA indicator; export), new
ticket (mobile-first, attachments), ticket detail (conversation with internal notes, attachments,
history timeline, actions per role, rating), helpdesk settings (categories, SLA matrix, business
calendar), documents, my exports, helpdesk report (charts: by status, organization, category; SLA
compliance; average resolution time), dashboard widgets.

## 8. Testing & acceptance

Backend: business calendar (weekend, lunch, holidays, multi-day, between), number generator
concurrency-safe uniqueness, visibility (employee own only, cross-org 404), workflow map (every
illegal transition 422/403), SLA due dates/pause/first response/breach job/auto-close, attachments
(type/size/MIME, cross-tenant download 404), notifications recipients, exports (scope applied in
job, owner-only download), documents versions/links, stats correctness, permissions per role.
Frontend: create form validation + upload, detail actions per role, list filters/URL, SLA badge,
settings forms. Gates as Phase 1 (`make check`), prod-mode smoke, independent review, merge.
