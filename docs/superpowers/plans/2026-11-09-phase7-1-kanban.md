# Phase 7.1 (Kanban) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Boards with configurable columns, tasks with drag-and-drop, checklist, comments, files, labels, history,
recurring tasks, ticket → task, reminders and workload.

**Architecture:** FormRequest → `app/Services/Tasks/*` → models with `BelongsToOrganization` (boards) and board-derived
visibility for tasks. Every move/reorder runs in one transaction under the board row lock. Frontend `src/features/tasks`
with `@dnd-kit` for drag-and-drop plus a "move to" menu.

**Tech Stack:** Laravel 13 / PHP 8.3, React 19 + Mantine 9, @dnd-kit/core + @dnd-kit/sortable.

**Spec:** `docs/superpowers/specs/2026-11-09-phase7-1-kanban-design.md`

## Global Constraints

- Every endpoint re-checks organization scope; out-of-scope → 404. Jobs/commands apply scope explicitly.
- Status-like changes re-read the row with `lockForUpdate()`; counts/positions that guard correctness are locking reads.
- Never run backend tests while `make check` runs (shared test DB).
- No TODO stubs, mock APIs or hardcoded stats; uz + ru strings for every UI text.

## Review Focus

1. Two users dragging cards in the same column at once never produce duplicate or skipped positions — Task 3.
2. A user cannot see, move or comment on tasks of boards outside their organizations, nor assign people who cannot open
   the board — Tasks 2–3.
3. Moving into a done/cancelled column sets the completed date; moving out clears it; overdue is never shown for finished
   tasks — Task 3.
4. A recurrence creates exactly one task per occurrence date, also when the command runs twice — Task 4.
5. Deleting or re-categorising a column never leaves a board without an open or a done column, nor orphans tasks — Task 2.

---

### Task 1: Foundations
Permissions + defaults, migrations (spec §4), enums `TaskPriority`, `ColumnCategory`, `TaskEvent`,
`RecurrenceFrequency`, models + morph map, default column list. Tests: permissions per role.

### Task 2: Boards, columns, labels
CRUD `/task-boards` (default columns on create, archive), `/task-boards/{id}/columns` (add, rename, category, WIP,
reorder, delete empty), `/task-boards/{id}/labels`. Tests: scope, defaults, column rules.

### Task 3: Tasks
`TaskService`: create (number, position at column end), update (history of changed fields), move (board lock,
renumber, completed date), delete; checklist, comments, attachments (FileAccess), watchers, labels; list with filters
(`board, column, assignee, label, overdue, organization, search, mine`), board view payload; ticket → task. Tests.

### Task 4: Recurrences and reminders
`/task-recurrences` CRUD, `RecurrenceSchedule::next()`, command `tasks:daily` 07:55 (occurrences + due tomorrow /
overdue reminders once), notifications. Tests.

### Task 5: Workload, dashboard, exports, docs
`/tasks/workload`, dashboard block `tasks`, exports `tasks`, `task_workload`, docs (DATABASE §10, API §14,
OPEN_QUESTIONS, ROADMAP with the 7.x split).

### Task 6–7: Frontend
6 boards list + board view (dnd-kit columns/cards, move menu, filters, overdue badges) + column/label settings;
7 task drawer (fields, checklist, comments, files, watchers, history), recurrences page, workload page, ticket button,
dashboard tile, navigation, notification types. Vitest for the main flows.

### Task 8: Verification
`make check`, browser e2e (desktop + 390px), review, fixes, merge, report.
