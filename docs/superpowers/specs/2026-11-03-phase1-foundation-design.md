# Phase 0 + Phase 1 (Foundation) — Design Spec

**Project:** AT.urtg.uz — Axborot texnologiyalari xizmatini boshqarish tizimi
**Date:** 2026-11-03 · **Status:** approved design, awaiting spec review
**Source requirements:** `AT_urtg_uz_Claude_Code_Prompt.md` (sections 1–16)
**Detailed references (Uzbek, team-facing):** `docs/ARCHITECTURE.md`, `docs/DATABASE.md`,
`docs/API.md`, `docs/ROADMAP.md`, `docs/OPEN_QUESTIONS.md`

## 1. Goal

Deliver a production-grade foundation that every later module (Helpdesk, CMDB,
warehouse, monitoring, …) plugs into without rework: auth, organization model,
RBAC with mandatory organization scoping, audit, settings, notifications base,
app shell UI, and Docker infrastructure. End state: `docker compose up -d --build`
on a clean machine yields a healthy stack where a Super Admin created via CLI
logs in, is forced to change the password, and manages organizations,
departments, employees, users, roles, and settings, with every change audited
and subsidiary users unable to see other organizations' data.

## 2. Scope

**In:** everything listed under "1-bosqich" in `docs/ROADMAP.md`, plus Phase 0
docs (README, ARCHITECTURE, ROADMAP, DATABASE, API, OPEN_QUESTIONS).

**Out (later phases):** Helpdesk and all other business modules, Excel/PDF export
(Phase 2, together with the export queue infrastructure), file uploads/documents
(Phase 2), MFA (columns reserved only), Russian translations (structure only),
Telegram/SMS/email notification channels beyond password-reset mail.

No UI element ships without a working backend action; the dashboard shows only
Phase 1 metrics, with no placeholder widgets for future modules.

## 3. Decisions

| ID | Decision | Rationale |
|---|---|---|
| D1 | Sanctum SPA cookie sessions (user-approved) | No JS-readable token; CSRF via XSRF cookie |
| D2 | Global roles + `user_organization_access` (user-approved) | Simple checks; `include_descendants` covers future orgs |
| D3 | Username login, optional email (user-approved) | Not all staff have corporate email |
| D4 | Laravel 13, PHP 8.3, MySQL 8.4, Mantine 9, React 19, TS ~6.0 (user-approved) | Current stable; TS 7 not yet supported by typescript-eslint |
| D5 | Org isolation = global scope + policy + form-request rule (approach A) | Out-of-scope = 404 incl. route binding; one trait per new module |
| D6 | Materialized path on organizations | Subtree query in one indexed `LIKE` |
| D7 | Sessions in DB, cache/queue in Redis | Revoke sessions per user on deactivate/reset |
| D8 | Own `Auditable` trait + append-only `audit_logs` | Full control over excluded fields and immutability |
| D9 | `spatie/laravel-query-builder` ^6.4 (v7 needs PHP 8.4) | Allowlisted filter/sort |
| D10 | Users table has nullable `organization_id` (home org) | Needed for scoping user management; must equal employee's org when linked; NULL only for system accounts |
| D11 | Compose env selection via `COMPOSE_FILE` in `.env` | Same `docker compose up -d --build` in every environment |
| D12 | Tests run inside Docker against `<db>_test` (phpunit env `force="true"`) | Host lacks pdo_sqlite; container env would otherwise override phpunit env |

## 4. Backend components

| Unit | Responsibility | Depends on |
|---|---|---|
| `Support\ApiResponse` + exception rendering in `bootstrap/app.php` | Uniform `{data,meta}` / `{message,code,errors}` envelopes | — |
| Middleware `AssignRequestId`, `SetLocale`, `EnsureUserIsActive`, `EnsurePasswordIsChanged` | Request context, locale, account state gates | Auth |
| `Support\OrganizationAccess` | Computes accessible org IDs (memoized per request); `canAccess(id)`, `isUnrestricted()` | organizations.path, user_organization_access |
| `Models\Concerns\BelongsToOrganization` + `Scopes\OrganizationScope` | Global scope active only when `Auth::hasUser()` | OrganizationAccess |
| `Models\Concerns\HasUserStamps` | created_by/updated_by | Auth |
| `Models\Concerns\Auditable` + `Services\Audit\AuditLogger` | Append-only audit rows with request context; secret fields excluded | Request |
| `Rules\AccessibleOrganization` | Validates incoming org IDs against scope (+active for new records) | OrganizationAccess |
| `Services\Organizations\OrganizationService` | Create (path), move (subtree path rewrite, cycle check), delete guards, IT responsibles | DB tx |
| `Services\Employees\EmployeeService` | CRUD, dismissal → `EmployeeDismissed` event | Events |
| `Listeners\DeactivateLinkedUser` | Deactivates user + kills sessions on dismissal | UserService |
| `Services\Users\UserService` | Create (temp password), update, activate/deactivate, admin password reset, session revocation | DB tx |
| `Services\Users\AccessManager` | Role sync + org access sync with escalation guards | OrganizationAccess, permissions |
| `Support\Permissions\PermissionRegistry` (`config/permissions.php`) | Single source of permission names, grouped by module, default role matrix | — |
| `Services\Settings\SettingsService` (`config/settings.php`) | Typed settings with defaults, validation, cache, public subset | Cache |
| `Services\Dashboard\DashboardService` | Permission-gated Phase 1 metrics | Models |
| `Services\Search\GlobalSearch` | Scoped search across orgs/employees/users | Models |
| `Notifications\AccessChanged`, `PasswordResetByAdmin` | Database-channel notifications (queued) | Queue |
| `Console\Commands\CreateSuperAdmin` | Interactive or `--from-env`; idempotent; forces password change | UserService |
| Seeders: `PermissionSeeder`, `RoleSeeder`, `SettingsSeeder` (always), `DemoSeeder` (non-production only) | Idempotent (`updateOrCreate`/`firstOrCreate`) | Registry |

## 5. Frontend components

`app/` (providers, router, theme), `api/client.ts` (Axios: credentials, XSRF,
Accept-Language, 401/419/403-password handling, `ApiError`), shared components
(`DataTable` with URL-synced params, `PageHeader`, `EmptyState`, `ErrorState`,
`StatusBadge`, `ConfirmDialog`, `OrganizationSelect`, `PermissionGate`), layouts
(`AppLayout` with collapsible sidebar/mobile drawer and header with breadcrumbs,
Spotlight search, notifications bell, org context switcher, profile menu;
`AuthLayout`), feature pages: login, forgot/reset password, forced password
change, dashboard, organizations (tree + detail + IT responsibles), departments,
employees, users (+ roles/access editor, reset password, deactivate), roles
(permission matrix editor), audit log (list + diff detail), settings, profile,
notifications, 403/404 pages. i18n `uz` complete, `ru` scaffold. Dates via
`lib/format.ts` (Asia/Tashkent, `DD.MM.YYYY HH:mm`).

## 6. Error handling

- Backend: all exceptions rendered as the error envelope; `ModelNotFound` and
  out-of-scope → 404 `not_found`; `AuthorizationException` → 403 `forbidden`;
  domain conflicts throw `App\Exceptions\DomainConflictException` → 409 `conflict`;
  500 responses contain only a generic message and `request_id`.
- Frontend: `ApiError` normalization; 422 mapped onto form fields; toast for
  other mutation errors; query errors render `ErrorState` with retry;
  global 401 → login, 419 → refresh CSRF and retry once.

## 7. Testing & acceptance

Backend (PHPUnit, MySQL test DB in Docker):
- Auth: login success/failure, throttle (429), inactive user, must-change-password
  gate, password change revokes other sessions, forgot/reset (no user enumeration).
- Isolation: subsidiary user gets 404 on another org's organization/department/
  employee/user (show, update, delete) and never sees them in lists or search;
  creating a record in an inaccessible org → 422.
- RBAC: per-role permission checks on each endpoint group; escalation guards
  (non-super-admin cannot grant super-admin / superset roles / out-of-scope access /
  modify self; last super admin protected).
- Org tree: path on create, subtree move, cycle rejection, delete guards.
- Audit: rows for create/update/delete and auth events; secrets excluded;
  AuditLog update/delete throws.
- Employee dismissal deactivates linked user and removes sessions.
- Seeders idempotent; `app:create-super-admin --from-env` idempotent.
- Unit: OrganizationAccess resolution, PermissionRegistry.

Frontend (Vitest + RTL + MSW in tests only): login form validation & error
display, forced password change redirect, `PermissionGate`, `DataTable`
empty/error/retry states, employee form Zod validation.

Quality gates: Pint, Larastan (level 6), ESLint, `tsc --noEmit` (strict),
`vite build`.

**Acceptance:** all of the above green; `docker compose up -d --build` on a clean
checkout → all services healthy; manual smoke test through the browser of the
end-state flow in §1; README lets a new developer install from scratch.

## 8. Risks

| Risk | Mitigation |
|---|---|
| Global scope surprises (jobs, console, auth recursion) | Scope only when `Auth::hasUser()`; documented; tests for console/jobs |
| Very new major versions (Mantine 9, React Router 8, Vite 8) API drift | Verify APIs from installed package types before use; pin exact versions in lockfiles |
| Port/name clashes with other projects on the host | Compose project `aturtg`, dev port 8095, MySQL not published by default |
