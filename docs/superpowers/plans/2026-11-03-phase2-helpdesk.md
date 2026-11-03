# Phase 2 (Helpdesk) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the Helpdesk module (tickets, business-hours SLA, workflow, comments, files, notifications,
stats, Excel/PDF export) plus the reusable documents/file/export foundations.

**Architecture:** Same layering as Phase 1: thin controllers → FormRequests → services (`app/Services/Helpdesk/*`)
→ Eloquent models with `BelongsToOrganization` + `Auditable`; policies for object checks; feature-folder
frontend (`src/features/helpdesk`, `documents`, `exports`) on TanStack Query.

**Tech Stack:** Laravel 13 / PHP 8.3, openspout/openspout ^5.3, barryvdh/laravel-dompdf ^3.1; React 19, Mantine 9.7
(+ `@mantine/dropzone`, `@mantine/charts` + recharts), MSW tests.

**Spec:** `docs/superpowers/specs/2026-11-03-phase2-helpdesk-design.md`

## Global Constraints

- No secrets/TODO stubs/mock APIs/hardcoded stats in production code; every button calls a real API.
- Every list/detail/download re-checks organization scope server-side; out-of-scope → 404.
- Jobs and commands have no auth user: they must apply scope explicitly (OrganizationScope is inactive there).
- Seeders idempotent; demo data only in local/development/testing.
- Files on the private disk only, never a public URL; downloads go through a policy-checked endpoint.
- Business time zone `config('app.display_timezone')` (Asia/Tashkent); DB timestamps stay UTC.
- UI strings in uz + ru locale files; Uzbek apostrophe ʻ/ʼ handled by the existing font setup.
- Gates: `make check` (Pint, Larastan 6, PHPUnit, ESLint, tsc, Vitest, build) green before merge.

## Review Focus

1. A ticket created by an employee without `helpdesk.view` must stay invisible to other employees of the same org (list, show, file download, export, stats, search) — test in Tasks 5, 7, 9.
2. Ticket created Friday 17:30 with 120-min response SLA is due Monday 10:30 (lunch/weekend/holiday aware) — Task 2.
3. Uploads renamed to `.pdf` but containing PHP/HTML/SVG-script are rejected — Task 3.
4. Export job run by queue applies the requester's scope and visibility, not "everything" — Task 9.
5. Concurrent ticket creation never produces duplicate numbers; year rollover restarts at 000001 — Task 5.

---

### Task 1: Foundations — packages, settings, permissions, schema, enums

**Files:** `backend/composer.json`; `config/settings.php`, `config/permissions.php`, `config/helpdesk.php`;
`app/Services/Settings/SettingsService.php` (array type + `item_rules`), `UpdateSettingsRequest.php`;
migrations `2026_11_04_1000xx_*` for every table in spec §3 (+ `users.notification_preferences` json);
`app/Enums/{TicketStatus,TicketPriority,TicketHistoryAction,DocumentType,ExportStatus}.php`;
`database/seeders/HelpdeskSeeder.php` (categories from spec §5.2 + default SLA policies, idempotent, runs in all envs);
`lang/{uz,ru}/enums.php`.

**Interfaces — Produces:**
- `TicketStatus` cases `New='new', InReview='in_review', Assigned='assigned', InProgress='in_progress', WaitingUser='waiting_user', Resolved='resolved', Closed='closed', Rejected='rejected'`; `isOpen(): bool` (not resolved/closed/rejected), `label()`.
- `TicketPriority` `Low, Normal, High, Critical` (values lowercase), `label()`, `weight(): int`.
- Settings keys: `helpdesk.work_days` (array, default `[1,2,3,4,5]`, ISO weekday), `helpdesk.work_start` `'09:00'`, `helpdesk.work_end` `'18:00'`, `helpdesk.lunch_start` `'13:00'`, `helpdesk.lunch_end` `'14:00'`, `helpdesk.holidays` (array of `Y-m-d`, default `[]`), `helpdesk.auto_close_days` 3, `helpdesk.reopen_days` 7, `helpdesk.due_soon_minutes` 60, `files.max_upload_mb` 10, `notifications.mail_enabled` true.
- Permissions per spec §5 added to `config/permissions.php` modules + default roles.
- Default SLA (business minutes, response/resolution): critical 30/240, high 60/480, normal 120/1440, low 480/2400.

- [ ] Tests first: `tests/Feature/Settings/ArraySettingTest` (array setting validates items, persists, rejects bad date/weekday), `tests/Feature/Rbac/HelpdeskPermissionsTest` (seeded roles get spec §5 defaults; reseed keeps admin edits), `tests/Feature/Seeders/HelpdeskSeederTest` (9 categories, 4 default policies, idempotent twice, runs in production).
- [ ] Run → FAIL; implement; run → PASS; `composer require openspout/openspout:^5.3 barryvdh/laravel-dompdf:^3.1`; commit `feat(helpdesk): settings, permissions, schema and seed data`.

### Task 2: BusinessCalendar

**Files:** `app/Services/Helpdesk/BusinessCalendar.php`; test `tests/Unit/BusinessCalendarTest.php` (uses settings via container, so Feature-style `TestCase`).

**Produces:** `addMinutes(CarbonInterface $start, int $minutes): CarbonImmutable` (UTC result),
`minutesBetween(CarbonInterface $from, CarbonInterface $to): int` (business minutes, 0 if `$to <= $from`).
Windows per day: `[work_start, lunch_start)`, `[lunch_end, work_end)` in display tz; skip non-work days and holidays.

- [ ] Tests: Mon 10:00 +120 → Mon 12:00; Mon 12:30 +60 → Mon 14:30 (lunch); Fri 17:30 +120 → Mon 10:30; Sat 11:00 +30 → Mon 09:30; holiday Monday skipped → Tue; 0 minutes from Sun → next opening Mon 09:00? (Ruling: `addMinutes(x, 0)` returns `x` unchanged); multi-day 1440 from Mon 09:00 → Wed 18:00; `minutesBetween(Fri 17:00, Mon 10:00)` = 120; times expressed in Asia/Tashkent, results compared in UTC.
- [ ] FAIL → implement → PASS → commit `feat(helpdesk): business-hours calendar`.

### Task 3: Stored files & safe uploads

**Files:** `app/Models/StoredFile.php`, `app/Services/Files/FileStore.php`, `app/Rules/SafeUpload.php`,
`app/Http/Controllers/Api/V1/Files/FileDownloadController.php`, `app/Services/Files/FileAccess.php`.

**Produces:** `FileStore::store(UploadedFile $file, ?int $organizationId, User $by): StoredFile` (disk `local`, path `files/Y/m/<uuid>`, sha256, mime from finfo);
`FileStore::storeContents(string $contents, string $name, string $mime, ?int $organizationId, ?User $by): StoredFile`;
`SafeUpload` rule: size ≤ `files.max_upload_mb`, extension in allowlist (jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,txt,csv,zip,rar,7z), sniffed MIME matches extension family, rejects any content starting with `<?php`, `<script`, `<html`, `<svg`.
`FileAccess::canDownload(User, StoredFile): bool` — delegates to registered resolvers (ticket attachment → TicketPolicy view + internal-comment rule; document version → DocumentPolicy view; report export → owner). Route `GET /files/{storedFile:uuid}` → stream download, 404 when not allowed.

- [ ] Tests `tests/Feature/Files/SafeUploadTest` (php disguised as pdf, svg, html, exe, oversize → 422; pdf/png/docx ok), `FileDownloadTest` (no resolver → 404; Content-Disposition attachment; never under `storage/app/public`).
- [ ] FAIL → implement → PASS → commit.

### Task 4: Categories & SLA policies

**Files:** models `TicketCategory`, `TicketSlaPolicy`; `Services/Helpdesk/SlaResolver.php`; controllers `Helpdesk/TicketCategoryController` (apiResource, index for any helpdesk user; write `helpdesk.manage`), `Helpdesk/SlaPolicyController`; `Helpdesk/CalendarSettingsController` (GET/PUT `helpdesk/calendar`, `helpdesk.manage`, wraps SettingsService for `helpdesk.*`).

**Produces:** `SlaResolver::for(TicketPriority $p, ?int $categoryId): array{response:int, resolution:int}` (category-specific row first, then priority-only row, then `config('helpdesk.default_sla')`).

- [ ] Tests: CRUD + uniqueness of (priority, category), category with tickets cannot be deleted (409) but can be deactivated, resolver precedence, calendar endpoint validates `work_end > work_start` and lunch inside the day, permission 403s.
- [ ] FAIL → implement → PASS → commit.

### Task 5: Tickets — numbering, create, list, show, edit, visibility

**Files:** models `Ticket` (table `helpdesk_tickets`), `TicketCounter`; `Services/Helpdesk/{TicketNumberGenerator,TicketService}.php`; `Policies/TicketPolicy.php`; `Http/Requests/Helpdesk/{StoreTicketRequest,UpdateTicketRequest}.php`; `Resources/Helpdesk/{TicketResource,TicketListResource}.php`; `Controllers/Api/V1/Helpdesk/TicketController.php`.

**Produces:**
- `TicketNumberGenerator::next(CarbonInterface $at): string` → `HD-2026-000001` (row lock `FOR UPDATE` on `ticket_counters.year`).
- `Ticket::scopeVisibleTo(Builder, User)` — `helpdesk.view` holders: no extra filter (OrganizationScope applies); others: `requester_user_id = user.id` OR `requester_employee_id = user.employee_id`.
- `TicketService::create(array $data, User $by): Ticket` — fills org (default `$by->organization_id`), requester employee (default `$by->employee_id`; handlers may choose an employee of that org), SLA snapshot and due dates via `SlaResolver` + `BusinessCalendar`, history `created`, dispatches `TicketCreated`.
- `TicketService::update(Ticket, array, User): Ticket` — requester may edit title/description/category/priority/contact only while `new`; handler (`helpdesk.process`) may edit category/priority any time while open (priority/category change recomputes due dates from `created_at`, history `field_changed`).
- List filters: `status` (csv), `priority`, `category`, `organization` (subtree), `assignee` (`me`|id|`none`), `requester` (`me`), `overdue` (bool), `created_from/to`; `search` over number/title/contact; sorts `created_at, priority, resolution_due_at, number`; tabs map to `filter[requester]=me`, `filter[assignee]=me`.

- [ ] Tests `tests/Feature/Helpdesk/TicketCreateTest`, `TicketVisibilityTest` (employee sees own only; colleague 404; cross-org handler 404; requester sees ticket even after it is assigned elsewhere), `TicketNumberTest` (sequential, unique under 20 rapid creates, new year restarts), `TicketUpdateTest` (requester edit after `in_review` → 403; handler priority change recomputes due).
- [ ] FAIL → implement → PASS → commit.

### Task 6: Workflow, assignment, comments, time, rating, history

**Files:** `Services/Helpdesk/TicketWorkflow.php`, `TicketCommentService.php`; models `TicketComment`, `TicketHistory`; controllers `TicketActionController` (`assign`, `take`, `transition`, `rate`, `time`), `TicketCommentController`, `TicketHistoryController`, `AssigneeController` (`GET helpdesk/assignees?organization_id=` — users with `helpdesk.process` who can access that org); events `TicketAssigned`, `TicketStatusChanged`, `TicketCommented`.

**Produces:** `TicketWorkflow::transition(Ticket, TicketStatus $to, User $by, ?string $note): Ticket` enforcing spec §4 map
(handler vs requester), `resolved` requires note→`resolution`, `rejected` requires note→`reject_reason`; entering `waiting_user` sets `paused_at`; leaving it adds `minutesBetween(paused_at, now)` to `paused_minutes` and shifts `resolution_due_at` by that many business minutes; reopen (`resolved|closed → in_progress`) within `reopen_days` (calendar days since resolved_at/closed_at) increments `reopened_count`, clears resolved_at/closed_at.
`assign(Ticket, User $assignee, User $by)`; `take(Ticket, User $by)`; `recordFirstResponse(Ticket, User $by)` — sets `first_response_at` once when actor is a handler and not the requester.
`rate(Ticket, int 1..5, ?string comment, User requester)` — only requester, only resolved/closed; `logTime(Ticket, int minutes 1..1440, User)` — handler; adds to `time_spent_minutes`.
Comments: requester reply while `waiting_user` moves to `in_progress`; internal comments require `helpdesk.process`, hidden from users without it (resource + query).

- [ ] Tests `TicketWorkflowTest` (data-provider over the full map: allowed pass, every other pair 422 `invalid_transition`), `TicketAssignTest` (assignee lacking process or org access → 422), `TicketSlaPauseTest`, `TicketFirstResponseTest`, `TicketCommentTest` (internal hidden from requester in list and history), `TicketRateTest`, `TicketReopenTest` (after 7 days → 422).
- [ ] FAIL → implement → PASS → commit.

### Task 7: Attachments & linked tickets

**Files:** model `TicketAttachment`, `TicketLink`; controllers `TicketAttachmentController` (`POST` multipart `files[]` max 5, optional `comment_id`; `DELETE` own within 15 min or handler), `TicketLinkController` (`POST {ticket_id, type: relates|duplicates}`, `DELETE`); register ticket resolver in `FileAccess`.

- [ ] Tests: upload on create (`StoreTicketRequest` accepts `files[]`), attachment on internal comment not downloadable by requester (404), colleague without visibility 404, cross-org 404, link requires both tickets visible, self-link 422, duplicate link 409.
- [ ] FAIL → implement → PASS → commit.

### Task 8: Notifications, SLA monitor, auto-close

**Files:** `app/Notifications/Channels/ChannelResolver.php`; notifications `Helpdesk/{TicketCreatedNotification,TicketAssignedNotification,TicketStatusChangedNotification,TicketCommentedNotification,TicketSlaBreachedNotification,TicketDueSoonNotification}`, `ExportReadyNotification`; listeners; commands `helpdesk:check-sla` (every 5 min), `helpdesk:auto-close` (hourly) in `routes/console.php`; `MeController` + profile: `notification_preferences` (`mail_muted: list<type>`).

**Produces:** `ChannelResolver::for(User $u, string $type): list<string>` → `['database']` + `'mail'` when `notifications.mail_enabled`, `$u->email`, and type not muted. Recipients: created → active users with `helpdesk.assign` who can access the ticket org (not the creator); assigned → assignee (unless actor); status changed → requester user (unless actor); public comment → requester or assignee (not author); internal → assignee (not author); due soon / breach → assignee, else dispatchers; each breach/due-soon notice sent once (`*_notified_at` / `*_breached_at`).

- [ ] Tests `TicketNotificationsTest` (`Notification::fake()` recipients per event; muted mail type → database only; mail disabled globally), `CheckSlaCommandTest` (marks breach once, idempotent; paused tickets not breached), `AutoCloseCommandTest` (resolved older than 3 business days → closed with history; younger untouched).
- [ ] FAIL → implement → PASS → commit.

### Task 9: Stats, dashboard, exports

**Files:** `Services/Helpdesk/TicketStats.php`; `Helpdesk/StatsController` (`GET helpdesk/stats?from&to&organization`); `DashboardService` helpdesk block; model `ReportExport`; `Services/Exports/{ExportService,TicketExportQuery,XlsxWriter,PdfWriter}.php`; `Jobs/GenerateExportJob.php`; `Exports/ExportController` (`POST exports {type:'helpdesk_tickets', format, filters}`, `GET exports` own, `GET exports/{uuid}` own); view `resources/views/exports/tickets.blade.php`; command `exports:prune` (daily, deletes > 7 days with files).

**Produces:** `TicketStats::summary(User $u, array $filters): array{by_status, by_priority, by_category, by_organization, sla:{response_met, response_total, resolution_met, resolution_total}, avg_resolution_minutes, open, overdue}` — built from `Ticket::visibleTo($u)` with explicit `AccessScope` (works in jobs too).
`TicketExportQuery::for(User $u, array $filters): Builder` — `withoutGlobalScopes()` + explicit scope + `visibleTo` + same filters as the list endpoint (shared filter class `TicketFilters`).

- [ ] Tests `TicketStatsTest` (exact counts on a fixture of 6 tickets across 2 orgs; restricted user sees only own org), `DashboardHelpdeskTest`, `ExportTest` (job with sync queue creates xlsx readable by openspout with N rows; restricted user's export excludes other org; PDF renders Cyrillic title; other user downloading → 404; `helpdesk.export` required).
- [ ] FAIL → implement → PASS → commit.

### Task 10: Documents base

**Files:** models `Document`, `DocumentVersion`, `DocumentLink`; `DocumentPolicy`; `Services/Documents/DocumentService.php`; controllers `Documents/{DocumentController,DocumentVersionController,DocumentLinkController}`; morph map `ticket => Ticket`.

**Produces:** document CRUD (`documents.view` list/show, `documents.manage` write), `POST documents/{id}/versions` (file, note) → version n+1, `current_version_id`; links `POST documents/{id}/links {type:'ticket', id}` only to objects the user can view; `GET helpdesk/tickets/{id}/documents`. File resolver for versions.

- [ ] Tests `DocumentCrudTest`, `DocumentVersionTest` (numbering, download via `/files/{uuid}` with org check), `DocumentLinkTest` (cannot link invisible ticket → 404).
- [ ] FAIL → implement → PASS → commit.

### Task 11: Backend docs

`docs/DATABASE.md` §5 Phase 2 tables + matrix rows, `docs/API.md` §8, `docs/OPEN_QUESTIONS.md` (new questions with defaults), `docs/ROADMAP.md` status. Scribe/Scramble docs test still green. Commit.

### Task 12: Frontend — helpdesk API layer & list

**Files:** `frontend/package.json` (+ `@mantine/dropzone`, `@mantine/charts`, `recharts`); `src/features/helpdesk/{types,api,queries,constants}.ts`; `TicketsPage.tsx`, `TicketsTable.tsx`, `SlaBadge.tsx`, `PriorityBadge.tsx`, `TicketStatusBadge.tsx`; navigation item `helpdesk` (permission any of `helpdesk.view|helpdesk.create`: extend `NavItem.permission` to `string | string[]`); locales `helpdesk.json` uz/ru; MSW handlers.

**Produces:** `slaState(ticket, now): 'ok'|'soon'|'breached'|'paused'|'done'` (pure, tested).

- [ ] Tests `slaState.test.ts`, `TicketsPage.test.tsx` (tabs set filters in URL; filters; export button only with `helpdesk.export`), `navigation.test.ts` (any-of permission).
- [ ] FAIL → implement → PASS → commit.

### Task 13: Frontend — create ticket

`NewTicketPage.tsx` (route `/helpdesk/new`), `schemas.ts` (zod: title 5..200, description 10..5000, category required, priority default normal, contact phone pattern optional, email optional), `AttachmentDropzone.tsx` (client-side size/type check mirroring server, max 5). Handlers see organization/requester selects; employees do not. Mobile: single column, sticky submit.

- [ ] Tests `NewTicketPage.test.tsx` (validation messages; submit multipart with files → navigates to detail; oversize file rejected before upload).
- [ ] FAIL → implement → PASS → commit.

### Task 14: Frontend — ticket detail

`TicketDetailPage.tsx` (`/helpdesk/:id`): header (number, status, priority, SLA), facts panel, conversation (`CommentThread`, internal toggle only for handlers, internal notes visibly marked), attachments list (download via `/api/v1/files/{uuid}`), history timeline, actions bar driven by `allowedActions` from the API resource (`TicketResource.allowed_actions: list<string>` — server computes; UI never guesses), dialogs: assign (assignee select), transition with required note for resolve/reject, log time, rate (Mantine Rating), link ticket, related documents.

- [ ] Tests `TicketDetailPage.test.tsx` (requester sees no internal toggle and no assign; handler resolve requires note; rate visible only when resolved for requester; actions call the right endpoints).
- [ ] FAIL → implement → PASS → commit.

### Task 15: Frontend — settings, reports, documents, exports, dashboard

`HelpdeskSettingsPage.tsx` (tabs: categories table + drawer, SLA matrix editor, business calendar form with holidays as date-picker list); `HelpdeskReportPage.tsx` (filters org/date/category/status; bar charts by status/organization/category, SLA met %, avg resolution as stat tiles, per `dataviz` skill); `DocumentsPage.tsx` + `DocumentDetailPage.tsx` (versions upload, links); `ExportsPage.tsx` (my exports with status polling every 5 s while pending); dashboard helpdesk tiles; profile notification preferences; general SettingsPage hides `helpdesk.*` keys.

- [ ] Tests for each page's main interaction (settings save payload, report renders numbers from API, document version upload, export polling stops when done, profile mute toggle).
- [ ] FAIL → implement → PASS → commit.

### Task 16: Verification

`make check`; prod-mode smoke (`aturtg-prodcheck`: migrate, seed roles+helpdesk, create ticket via API, queue worker generates export, scheduler commands run); Playwright screenshots of list/new/detail on desktop + 390px; fix findings; update ROADMAP ✅.
