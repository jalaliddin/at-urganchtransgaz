# Phase 7.1 (IT staff tasks — Kanban) — Design Spec

**Date:** 2026-11-09 · **Status:** autonomous process (user: build each phase, report after it)
**Source:** `AT_urtg_uz_Claude_Code_Prompt.md` §5.11, §5.14 (task due), §5.15 (staff tasks and workload), §8 task tables,
§13 Phase 7. Phase 7 is split into 7.1 Kanban, 7.2 printers and cartridges, 7.3 knowledge base / change / problem /
preventive maintenance, 7.4 security incidents, backups, IT passport and extended reports — each merged and reported.
**Builds on:** organizations scope, users/roles, Helpdesk tickets, FileStore + SafeUpload, NumberSequence,
ChannelResolver, ExportService, `open_key`/alerts-once pattern.

## 1. Goal

IT staff plan and track their work on boards: tasks move through configurable columns by drag-and-drop, carry a
responsible person, watchers, priority, dates, checklist, comments, files and labels; late tasks stand out; routine work
repeats by itself; a ticket can become a task; the central IT head sees the workload per person and organization.

## 2. Decisions

| ID | Decision | Rationale |
|---|---|---|
| K1 | Boards belong to an organization (scope) and are visible to users with access to it; archived boards are read-only | Same tenancy as every module |
| K2 | New board gets the default columns Backlog, Rejalashtirilgan, Jarayonda, Tekshiruvda, Bajarildi, Bekor qilindi; each column has a category `open`, `done` or `cancelled`; columns can be renamed, reordered, added, given a WIP limit (soft) and deleted when empty; a board keeps at least one open and one done column | §5.11 standard + configurable columns; categories decide "finished" and "overdue" |
| K3 | Task: number `T-YYYY-NNNNN`, title, description, organization (board's organization or one of its descendants), project/direction (free text), responsible user, watchers, priority (low, normal, high, urgent), start date, due date, labels, position in its column, completed date (set when it enters a done/cancelled column, cleared when it leaves) | §5.11 fields |
| K4 | Overdue = due date before today and the task in an open column; shown on cards, lists, dashboard | §5.11 indicator |
| K5 | Moving = column + position; positions are renumbered in the target column inside one transaction under the board row lock (no gaps, no duplicates under concurrent drags) | Drag-and-drop correctness |
| K6 | Checklist items (text, done, position), comments, attachments (FileStore, same visibility as the task), history of every change (created, updated fields, moved, assigned, checklist, comment, file, completed, reopened) | §5.11 |
| K7 | Responsible person and watchers are active users who hold `tasks.view` and can access the board's organization | Only people who can open the task get it |
| K8 | Recurring tasks: a recurrence on a board (title, description, responsible, priority, labels, target column, frequency daily/weekly/monthly with interval, weekday or month day, due in N days, next date, active). Daily `tasks:daily` creates due occurrences once per recurrence and date (`recurrence_id + occurrence_date` UNIQUE) | §5.11 recurring tasks |
| K9 | From a Helpdesk ticket: "create task" picks a board; the task copies title/description/organization and keeps the link both ways | §5.11 |
| K10 | Notifications: assigned to you; comment on a task you are responsible for or watch; due tomorrow and overdue (daily, once per task and due date) | §5.14 |
| K11 | Workload: per responsible user and per organization — open, overdue, due within 7 days, done in the last 30 days; filter by organization and board | §5.11 / §5.15 |
| K12 | Edit/move a task: `tasks.manage`, or its responsible person, or its creator; delete: `tasks.manage` or the creator while nothing was done on it | Ownership |

## 3. Permissions

`tasks.view, tasks.create, tasks.manage, tasks.workload, tasks.export`. Defaults: central-it-head → all;
central-it-staff → view/create/export; org-it-responsible → view/create/manage/export; org-head → view;
auditor → view/workload/export.

## 4. Data

`task_boards`, `task_columns`, `tasks`, `task_watchers`, `task_labels`, `task_label_task`, `task_checklist_items`,
`task_comments`, `task_attachments`, `task_histories`, `task_recurrences`, `task_alerts`. Columns: `docs/DATABASE.md`
§10 (Phase 7.1).

## 5. Testing & acceptance

Scope 404 everywhere; default columns; column delete/category rules; move renumbering (including two moves at once);
completed date on done/cancelled; overdue; assignee/watcher eligibility; edit rights; checklist/comments/files/history;
recurrence generation once per date and schedule maths; ticket → task link; reminders once; workload numbers; exports.
Frontend: boards, board with drag-and-drop (and a move menu for keyboard/phone), task drawer (all parts), workload page,
ticket "create task", dashboard tile. Gates as before + review + merge.
