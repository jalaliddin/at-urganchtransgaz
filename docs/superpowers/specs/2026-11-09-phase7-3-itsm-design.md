# Phase 7.3 (Knowledge base, Problem, Change, Preventive maintenance) — Design Spec

**Date:** 2026-11-09 · **Status:** autonomous process (user: build each phase, report after it)
**Source:** `AT_urtg_uz_Claude_Code_Prompt.md` §6 (knowledge base: FAQ, guides, solutions; change management: network,
server and important configuration changes; problem management: recurring problems and root cause; preventive
maintenance: service schedule for servers, UPS, printers and network devices), §5.14 notifications, §8 tables
(`kb_articles`, `changes`, `problems`, `maintenance_plans`), §13 Phase 7.
**Builds on:** Helpdesk tickets and categories, assets (CMDB) and asset events, FileStore + SafeUpload, NumberSequence,
ChannelResolver, ExportService, settings, `open_key`/alerts-once pattern, Kanban recurrence maths.

## 1. Goal

IT staff write down how things are solved and users find the answer before opening a ticket (knowledge base); recurring
incidents become problems with a root cause and a workaround (problem management); risky network, server and
configuration work is planned, approved and recorded against the affected devices (change management); servers, UPS,
printers and network devices get scheduled service with checklists and reminders (preventive maintenance).

## 2. Decisions

### Knowledge base

| ID | Decision | Rationale |
|---|---|---|
| B1 | Article: number `KB-NNNNN`, title, type (`faq`, `guide`, `solution`), helpdesk ticket category (optional), summary, body, audience (`all` — every signed-in user incl. employees; `it` — IT staff only), status (`draft`, `published`, `archived`), files, `published_at`, views, helpful / not helpful votes (one per user, changeable), source ticket (optional). Global, not organization-scoped | Company-wide knowledge; ticket categories already describe topics |
| B2 | Body is Markdown (headings, lists, bold/italic, inline and block code, links); the SPA renders it with its own renderer — no raw HTML ever reaches the page | Instructions need structure; no XSS surface, no new dependency |
| B3 | Rights: `kb.view` reads published `all` articles; `kb.internal` also published `it` articles; `kb.write` creates articles and edits, publishes and archives its own; `kb.manage` does that for any article and deletes. Drafts are seen by their author and `kb.manage`. Only never-published articles can be deleted (else archive) | Employees self-serve; quality stays with IT |
| B4 | Search over number, title, summary and body; filters type, category, audience, status. The new-ticket form suggests up to 5 published articles the user may read, matching the title words and the chosen category | Ticket deflection |
| B5 | "Create article" on a resolved/closed ticket opens the editor prefilled with its title, category, description and resolution and links the source ticket | Solutions come from real tickets |

### Problem management

| ID | Decision | Rationale |
|---|---|---|
| R1 | Problem: number `PRB-YYYY-NNNNN`, organization (scope), title, description, ticket category (optional), priority (`low`, `normal`, `high`, `critical`), assignee (active user with `problems.manage` and access to the organization), status `new` → `investigating` → `known_error` → `resolved` → `closed`; `resolved` → `investigating` (reopen); any open status → `closed` with a reason. Fields root cause, workaround, resolution, known-error KB article, `resolved_at`, `closed_at` | ITIL-lite lifecycle |
| R2 | `known_error` needs a workaround; `resolved` needs root cause and resolution; closing a problem that was not resolved needs a reason | Root cause is the point of the process |
| R3 | Linked tickets: tickets visible to the user inside the problem organization's subtree; the ticket page lists its problems | Incidents ↔ problem traceability |
| R4 | Recurring candidates: ticket category × organization with at least `problems.recurrence_threshold` (setting, default 3) tickets in the last 30 days that are not linked to an open problem; "create problem" from a candidate prefills it and links those tickets | §6 "takrorlanuvchi muammolar" |
| R5 | History of every change (created, updated, status, assigned, tickets linked/unlinked, comment); comments live in the history | Audit |
| R6 | Notifications: the assignee when assigned; when resolved, the assignees of its still-open linked tickets | Close the loop |

### Change management

| ID | Decision | Rationale |
|---|---|---|
| C1 | Change: number `CHG-YYYY-NNNNN`, organization (scope), title, description, type (`standard`, `normal`, `emergency`), category (`network`, `server`, `configuration`, `software`, `other`), risk (`low`, `medium`, `high`), impact, implementation plan, backout plan, planned start/end, actual start/end, implementer, affected assets (CMDB, organization subtree), problem (optional), result note | §6 network, server, configuration changes |
| C2 | Status: `draft` → submit → `pending_approval` (normal) → `approved` / `rejected`; standard and emergency become `approved` at submit (emergency notifies approvers); `approved` → `in_progress` → `implemented` / `failed`; `draft`, `pending_approval`, `approved` → `cancelled`; `rejected` → `draft` (revise). Plan fields are editable only in `draft` | Approval before risky work; standard changes are pre-approved |
| C3 | Rights: `changes.create` creates and edits its own drafts and submits them; `changes.approve` approves/rejects (not its own change); start/complete — the implementer or `changes.manage`; `changes.manage` edits/cancels any | Segregation of duties |
| C4 | Conflicts: other approved/in-progress changes whose planned window overlaps and that share an affected asset are listed on the change and at approval (warning, not a block) | Avoid two works on one device |
| C5 | Implemented/failed changes write an asset event `change_implemented` on every affected asset (number, result) | CMDB history |
| C6 | Notifications: approvers (holders of `changes.approve` with access to the organization) when a change waits for approval or an emergency change is submitted; requester and implementer on the decision; implementer when set | §5.14 |

### Preventive maintenance

| ID | Decision | Rationale |
|---|---|---|
| M1 | Plan: organization (scope), title, description, checklist steps, assets (organization subtree), interval (N `weeks` or `months`), start date, responsible user, reminder lead days (default 7), active | §6 service schedule |
| M2 | Work items (`maintenance_tasks`): plan × asset × due date (UNIQUE). Each asset has at most one open item; the next is created when the previous is done or skipped. Daily `maintenance:daily` (07:50) creates missing items, cancels items of removed/written-off assets and inactive plans, and sends "due soon" (within lead days) and "overdue" reminders once per item | No backlog pile-up; reminders once |
| M3 | Next due = previous due + interval, moved forward by whole intervals until it is today or later (missed cycles are not created) | Schedule stays anchored to the plan |
| M4 | Complete: per-step done flag and note, result `ok` / `issues`, note, date (not in the future); writes asset event `preventive_done`. Skip needs a reason | Evidence of service |
| M5 | Rights: `maintenance.manage` — plans; `maintenance.perform` — complete/skip items of the user's organizations | Field staff vs planners |

## 3. Permissions

`kb.view, kb.internal, kb.write, kb.manage`; `problems.view, problems.manage, problems.export`;
`changes.view, changes.create, changes.approve, changes.manage, changes.export`;
`maintenance.view, maintenance.manage, maintenance.perform, maintenance.export`.
Defaults: central-it-head → all; central-it-staff → kb view/internal/write, problems view/manage/export, changes
view/create/manage/export, maintenance view/manage/perform/export; org-it-responsible → kb
view/internal/write, problems view/manage/export, changes view/create/export, maintenance view/manage/perform/export;
org-head → kb.view, problems.view, changes.view, maintenance.view; employee, accountant → kb.view; auditor → kb
view/internal, problems view/export, changes view/export, maintenance view/export.

## 4. Data

`kb_articles`, `kb_article_attachments`, `kb_article_votes`; `problems`, `problem_tickets`, `problem_histories`;
`changes`, `change_assets`, `change_histories`; `maintenance_plans`, `maintenance_plan_assets`, `maintenance_tasks`,
`maintenance_alerts`. Columns: `docs/DATABASE.md` §12 (Phase 7.3).

## 5. Testing & acceptance

Scope 404 everywhere (problem, change, plan, task of another organization; ticket/asset links outside the subtree);
permissions per role; KB visibility by audience/status/author, votes once per user, search, ticket suggestions,
deletion only of never-published drafts; problem transitions and required fields, ticket links, recurring candidates;
change workflow per type, self-approval refused, conflicts, asset events; PM schedule maths, daily generation once,
reminders once, complete/skip creating the next item, cancellations; dashboard; exports. Frontend: KB list/article/
editor and ticket-form suggestions, problems list/detail, changes list/detail/form, PM plans and work list with the
completion form, ticket page problem panel, dashboard tiles. Gates as before + review + merge.
