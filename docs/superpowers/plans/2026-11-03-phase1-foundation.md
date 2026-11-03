# Phase 1 Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the AT.urtg.uz foundation: Dockerized Laravel 13 API + React/Mantine SPA with auth, organization tree, departments, employees, users/roles (RBAC with mandatory organization scoping), audit log, settings, notifications base, dashboard, global search.

**Architecture:** Monorepo (`backend/`, `frontend/`, `docker/`). Laravel API under `/api/v1` with Sanctum SPA cookie sessions; tenant isolation by global scope + policy + form-request rule. React SPA served behind an edge nginx that routes `/api|/sanctum|/docs|/up` to PHP-FPM.

**Tech Stack:** PHP 8.3, Laravel ^13, Sanctum ^4, spatie/laravel-permission ^8, spatie/laravel-query-builder ^6.4, dedoc/scramble, MySQL 8.4, Redis 7; React ^19, TypeScript ~6.0, Vite ^8, Mantine ^9, mantine-datatable ^9, React Router ^8, TanStack Query ^5, React Hook Form ^7 + Zod ^4, i18next, dayjs; PHPUnit, Larastan, Pint, Vitest, Testing Library, MSW.

**Spec:** `docs/superpowers/specs/2026-11-03-phase1-foundation-design.md` (+ `docs/ARCHITECTURE.md`, `docs/DATABASE.md`, `docs/API.md`, which hold table columns, the role×permission matrix and endpoint lists — implement exactly those).

## Global Constraints

- All endpoints under `/api/v1`. Success `{"data": …, "meta"?: …}`; message-only `{"message": …}`; error `{"message", "code", "errors"?}` with codes `bad_request|unauthenticated|forbidden|password_change_required|not_found|conflict|csrf_token_mismatch|validation_error|too_many_requests|server_error`.
- Out-of-scope (other organization) records → **404 `not_found`**, never 403.
- DB/API timestamps UTC, ISO 8601 with `Z`; dates `YYYY-MM-DD`. UI: `Asia/Tashkent`, `DD.MM.YYYY`, `HH:mm`. Money `DECIMAL(18,2)` UZS (none in Phase 1).
- Business tables carry `created_by`, `updated_by` (FK users, `nullOnDelete`), timestamps, `deleted_at`.
- No secrets in code or git; `.env` ignored; production `APP_DEBUG=false`, app DB user ≠ root, MySQL port never published in prod.
- Every UI string from i18n files (`uz` complete; `ru` files mirror `uz` keys). Backend messages from `lang/uz` (`lang/ru` mirrors keys).
- Project name string: `AT.urtg.uz — Axborot texnologiyalari xizmatini boshqarish tizimi`.
- No TODO stubs, no mock API or hardcoded statistics in production code; every button calls a real endpoint. MSW only under `src/test/`.
- Compose project `aturtg`; dev edge port `8095`; never stop/modify other containers on this host.
- Permission names `module.action` from `config/permissions.php`; role names `super-admin, central-it-head, central-it-staff, org-it-responsible, org-head, employee, accountant, auditor`.
- TypeScript `strict: true`; Larastan level 6; Pint default Laravel preset.
- Work on branch `feat/phase-1-foundation`; one commit per task (message ends with the Co-Authored-By trailer).

## Review Focus

1. **Uzbek apostrophe variants and LIKE wildcards in `search`** — `Gʻulomov` stored with `ʻ` must be found by typing `G'ulomov` (and vice versa); `%`/`_` match literally, never 500. → tests in Task 2 (`SearchTermTest`) and Task 10.
2. **Tashkent day boundary in date filters** — `filter[created_from]=2026-11-03` must include a row created `2026-11-02T19:30:00Z` (00:30 Tashkent) and `created_to=2026-11-02` must exclude it. → Task 12.
3. **Employee transferred to another organization while linked to a user** — user's home org follows; the plain (non-descendant) access row for the old org is replaced by one for the new org; no lingering access. → Task 10.
4. **Organization moved to another parent** — users with `include_descendants` on the old parent lose it immediately (404); users on the new parent gain it. → Task 9.
5. **Malformed list params** — unknown `sort`/`filter` → 400 `bad_request`; `per_page=1000` → 100; `per_page=abc` → 25; never 500. → Task 2 and Task 9.

---

## File Structure

```
compose.yaml, compose.dev.yaml, compose.prod.yaml, .env.example, Makefile
scripts/{init-env.sh, backup.sh, restore.sh, selfsigned-cert.sh, smoke.sh}
docker/php/{Dockerfile, php.ini, php-prod.ini, www.conf, entrypoint.sh}
docker/frontend/{Dockerfile, spa.conf}
docker/nginx/{dev.conf.template, prod.conf.template, snippets/{security-headers.conf, backend.conf}, certs/.gitkeep}
docker/mysql/init/01-test-db.sh
backend/  (Laravel 13 skeleton +)
  app/Support/{ApiResponse.php, Pagination.php, Search/SearchTerm.php}
  app/Support/Access/{AccessScope.php, OrganizationAccessResolver.php}
  app/Support/Permissions/{Roles.php, PermissionRegistry.php}
  app/Exceptions/DomainConflictException.php
  app/Http/Middleware/{AssignRequestId, SetLocale, EnsureUserIsActive, EnsurePasswordIsChanged}.php
  app/Http/Controllers/Api/V1/{Health,Auth,Settings,Organizations,Departments,Employees,Users,Roles,Notifications,Audit,Dashboard,Search}/…
  app/Http/Requests/<Module>/…, app/Http/Resources/<Module>/…, app/Policies/…
  app/Models/{Organization, Department, Employee, OrganizationItResponsible, User, UserOrganizationAccess, SystemSetting, Role, AuditLog}.php
  app/Models/Concerns/{HasUserStamps, BelongsToOrganization, Auditable}.php, app/Models/Scopes/OrganizationScope.php
  app/Enums/{OrganizationType, EmployeeStatus, AuditEvent}.php
  app/Rules/AccessibleOrganization.php
  app/Services/{Audit/AuditLogger, Settings/SettingsService, Auth/SessionRevoker, Auth/PasswordRules, Organizations/OrganizationService, Departments/DepartmentService, Employees/EmployeeService, Users/UserService, Users/AccessManager, Users/TemporaryPassword, Dashboard/DashboardService, Search/GlobalSearch}.php
  app/Events/{EmployeeDismissed, EmployeeTransferred}.php, app/Listeners/{DeactivateLinkedUser, SyncLinkedUserOrganization}.php
  app/Notifications/{AccessChanged, PasswordResetByAdmin, ResetPasswordLink}.php
  app/Console/Commands/CreateSuperAdmin.php
  config/{permissions.php, settings.php}
  database/seeders/{DatabaseSeeder, RbacSeeder, DemoSeeder}.php
  tests/Concerns/InteractsWithAccess.php
frontend/src/
  app/{App.tsx, providers.tsx, router.tsx, theme.ts, queryClient.ts}
  api/{client.ts, errors.ts, types.ts}
  i18n/{index.ts, locales/{uz,ru}/*.json}
  lib/{format.ts, dayjs.ts, forms.ts}
  stores/ui.ts
  hooks/{usePermissions.ts, useTableParams.ts}
  components/{DataTable/, PageHeader, EmptyState, ErrorState, StatusBadge, ConfirmDialog, PermissionGate, OrganizationSelect}
  layouts/{AuthLayout.tsx, AppLayout/…}
  features/{auth, dashboard, organizations, departments, employees, users, roles, audit, settings, profile, notifications, search, errors}/
  test/{setup.ts, server.ts, utils.tsx}
```

---

### Task 1: Monorepo scaffold and Docker stack

**Files:**
- Create: `backend/` (Laravel 13 skeleton), `frontend/` (Vite `react-ts` template), all `docker/**`, `compose*.yaml`, `.env.example`, `Makefile`, `scripts/init-env.sh`, `backend/.dockerignore`, `frontend/.dockerignore`
- Modify: `backend/phpunit.xml`, `backend/bootstrap/app.php`, `backend/config/{app,database,session,sanctum,logging}.php` via env defaults only where needed

**Interfaces:**
- Produces: compose services `nginx, frontend, backend, queue-worker, scheduler, mysql, redis, mailpit(dev)`; Make targets `up, down, build, logs, shell, migrate, seed, test-backend, test-frontend, lint, build-frontend, check` (check = all gates); env keys `COMPOSE_PROJECT_NAME=aturtg, COMPOSE_FILE, APP_PORT=8095, APP_ENV, APP_KEY, APP_DEBUG, APP_URL, SESSION_DOMAIN, SESSION_SECURE_COOKIE, SANCTUM_STATEFUL_DOMAINS, DB_HOST=mysql, DB_DATABASE=at_urtg, DB_USERNAME=at_urtg, DB_PASSWORD, DB_ROOT_PASSWORD, REDIS_HOST=redis, REDIS_PASSWORD, MAIL_*, SUPER_ADMIN_{USERNAME,PASSWORD,NAME,EMAIL}, DEMO_PASSWORD, UID=1000, GID=1000, TRUSTED_PROXIES, NGINX_SERVER_NAME`. Test DB name `at_urtg_test`.

- [ ] **Step 1: Branch and scaffold.** `git checkout -b feat/phase-1-foundation`; `composer create-project laravel/laravel:^13.0 backend`; `cd backend && php artisan install:api --no-interaction` (Sanctum + `routes/api.php`); `composer require spatie/laravel-permission:^8 spatie/laravel-query-builder:^6.4 dedoc/scramble`; `composer require --dev larastan/larastan`; `php artisan lang:publish`. `npm create vite@latest frontend -- --template react-ts`.
- [ ] **Step 2: Backend config.** `bootstrap/app.php`: `withRouting(api: …, apiPrefix: 'api/v1', health: '/up')`, `$middleware->statefulApi()`, `trustProxies(at: env('TRUSTED_PROXIES'))`. `config/app.php`: `timezone` = `UTC` (hard), `locale` = `uz`, `fallback_locale` = `en`. `.env`-driven: `SESSION_DRIVER=database`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `REDIS_CLIENT=phpredis`, `LOG_CHANNEL=stderr`. `phpunit.xml`: every `<env>` gets `force="true"`; values `APP_ENV=testing, DB_CONNECTION=mysql, DB_DATABASE=at_urtg_test, CACHE_STORE=array, QUEUE_CONNECTION=sync, SESSION_DRIVER=array, MAIL_MAILER=array, BCRYPT_ROUNDS=4, PULSE_ENABLED=false`.
- [ ] **Step 3: PHP image** `docker/php/Dockerfile` stages: `base` (`php:8.3-fpm-alpine`, exts `pdo_mysql intl zip bcmath opcache pcntl` + pecl `redis`, package `fcgi`, `www.conf` with `ping.path = /ping`), `dev` (adds composer binary; `ARG UID GID` → run as that uid), `vendor` (`composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader`), `prod` (copies app + vendor, `php-prod.ini` with `opcache.validate_timestamps=0`, owns `storage` and `bootstrap/cache` as `www-data`). `entrypoint.sh`: dev → `composer install` if `vendor/` missing; prod → `php artisan optimize`; if `RUN_MIGRATIONS=true` → `php artisan migrate --force`; then `exec "$@"`. Healthcheck: `SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong`.
- [ ] **Step 4: Frontend image** `docker/frontend/Dockerfile`: `dev` (`node:22-bookworm-slim`, runs `npm ci` if `node_modules` missing, then `npm run dev -- --host 0.0.0.0 --port 5173`), `build` (`npm ci && npm run build`), `prod` (`nginxinc/nginx-unprivileged:1.27-alpine`, `spa.conf`: port 8080, `try_files $uri /index.html`, immutable cache for `/assets/`, `no-cache` for `index.html`).
- [ ] **Step 5: Edge nginx** (`nginx:1.27-alpine`, conf via `/etc/nginx/templates`). `snippets/backend.conf`: `location ~ ^/(api|sanctum|docs|up)(/|$)` → `fastcgi_pass backend:9000`, `SCRIPT_FILENAME /var/www/html/public/index.php`, `client_max_body_size 25m`. `snippets/security-headers.conf`: `X-Content-Type-Options nosniff`, `X-Frame-Options DENY`, `Referrer-Policy strict-origin-when-cross-origin`, `Permissions-Policy camera=(), microphone=(), geolocation=()`, CSP `default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; font-src 'self' data:; frame-ancestors 'none'` (in dev add `ws:` to connect-src; `/docs` location overrides CSP to allow `https://unpkg.com`). `dev.conf.template`: listen 80, `/` → `http://frontend:5173` with websocket upgrade. `prod.conf.template`: 80 → 301 https (except `/.well-known/acme-challenge/`), 443 `ssl http2` with `/etc/nginx/certs/{fullchain,privkey}.pem`, TLSv1.2+1.3, HSTS `max-age=31536000`, `/` → `http://frontend:8080`. Both: `location = /nginx-health { return 200; }`.
- [ ] **Step 6: Compose.** `compose.yaml` (shared): `x-logging` json-file `max-size: 10m, max-file: "5"`; every service `restart: unless-stopped`, healthcheck, `depends_on: {condition: service_healthy}`; mysql `mysql:8.4` with `--character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci --default-time-zone=+00:00`, volume `mysql-data`, no ports; redis `redis:7-alpine --requirepass ${REDIS_PASSWORD} --appendonly yes`, volume `redis-data`; backend `env_file: .env`, volume `app-storage:/var/www/html/storage/app`; queue-worker `php artisan queue:work redis --sleep=1 --tries=3 --max-time=3600`; scheduler `php artisan schedule:work`. `compose.dev.yaml`: build targets `dev`, `user: ${UID}:${GID}`, bind mounts `./backend:/var/www/html`, `./frontend:/app`, `./docker/mysql/init:/docker-entrypoint-initdb.d:ro`, mysql `127.0.0.1:${DB_FORWARD_PORT:-33095}:3306`, mailpit `127.0.0.1:${MAILPIT_PORT:-8025}:8025`, nginx `${APP_PORT}:80`. `compose.prod.yaml`: targets `prod`, backend `RUN_MIGRATIONS=true`, nginx `80:80, 443:443` + `./docker/nginx/certs:/etc/nginx/certs:ro`. `01-test-db.sh` creates `${MYSQL_DATABASE}_test` and grants `ALL` on it to `${MYSQL_USER}`.
- [ ] **Step 7: `scripts/init-env.sh`** copies `.env.example` → `.env` only if absent, fills `APP_KEY` (`base64:` + 32 random bytes via `openssl rand -base64 32`), `DB_PASSWORD`, `DB_ROOT_PASSWORD`, `REDIS_PASSWORD`, `DEMO_PASSWORD` with random 24-char values; never overwrites existing values. `.env.example` has empty secrets and comments marking dev vs prod values (`COMPOSE_FILE=compose.yaml:compose.dev.yaml` dev default; prod line commented).
- [ ] **Step 8: Verify stack.** Run: `./scripts/init-env.sh && docker compose up -d --build && docker compose ps`. Expected: all services `healthy`/`running`. `curl -s -o /dev/null -w '%{http_code}' localhost:8095/up` → `200`; `curl -s localhost:8095/ | grep -c 'id="root"'` → `1`.
- [ ] **Step 9: Verify tests hit the test DB.** Run: `make test-backend`. Expected: Laravel example tests PASS; `docker compose exec -T mysql sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "SHOW DATABASES"'` lists `at_urtg_test`.
- [ ] **Step 10: Commit** `chore: scaffold Laravel 13 + Vite apps and Docker Compose stack`.

---

### Task 2: API conventions layer

**Files:**
- Create: `app/Support/ApiResponse.php`, `app/Support/Pagination.php`, `app/Support/Search/SearchTerm.php`, `app/Exceptions/DomainConflictException.php`, `app/Http/Middleware/{AssignRequestId,SetLocale}.php`, `app/Http/Controllers/Api/V1/Health/HealthController.php`, `lang/uz/{validation,auth,passwords,pagination,messages}.php`, `lang/ru/{validation,auth,passwords,pagination,messages}.php`
- Modify: `bootstrap/app.php` (middleware + exception rendering), `app/Providers/AppServiceProvider.php` (rate limiters), `routes/api.php`
- Test: `tests/Feature/Api/{ErrorEnvelopeTest,HealthTest,RequestIdTest,LocaleTest}.php`, `tests/Unit/{SearchTermTest,PaginationTest}.php`

**Interfaces:**
- Produces:
  - `ApiResponse::data(mixed $data, int $status = 200, array $meta = []): JsonResponse`
  - `ApiResponse::message(string $message, int $status = 200): JsonResponse`
  - `ApiResponse::error(string $message, string $code, int $status, array $errors = []): JsonResponse`
  - `ApiResponse::paginated(LengthAwarePaginator $page, class-string<JsonResource> $resource): JsonResponse` → `meta: {current_page, per_page, total, last_page, from, to}`
  - `Pagination::perPage(Request $r): int` (default 25, clamp 1..100, non-numeric → 25)
  - `SearchTerm::whereContains(Builder $q, array $columns, string $term): Builder` — escapes `\ % _`, normalises apostrophes `ʻ ʼ ‘ ’ \`` → `'` in the term, and compares against `REPLACE(...)`-normalised columns (OR across columns, grouped)
  - `DomainConflictException(string $message)` → 409 `conflict`
  - Rate limiters: `api` (120/min by user id or IP), `login`, `password-reset` (5/min IP), `public` (60/min IP); request attribute `request_id`; response header `X-Request-Id`
  - Middleware order (api group): `AssignRequestId`, `SetLocale` (user `locale` → `Accept-Language` → `uz`; supported `uz, ru`)

- [ ] **Step 1: Write failing tests.** Test-only routes registered in `setUp()` under `/api/v1/__test/*`.

```php
// ErrorEnvelopeTest
test_unknown_route_returns_not_found_envelope: getJson('/api/v1/nope')->assertNotFound()->assertJson(['code' => 'not_found'])->assertJsonStructure(['message','code']);
test_validation_error_envelope_in_uzbek: postJson('/api/v1/__test/validate', [])->assertStatus(422)->assertJsonPath('code','validation_error')->assertJsonStructure(['errors' => ['name']]); // message from lang/uz
test_server_error_hides_details: config(['app.debug'=>false]); route throws RuntimeException('SQLSTATE secret'); assertStatus(500)->assertJsonPath('code','server_error'); assertStringNotContainsString('SQLSTATE', $res->getContent()); assertJsonStructure(['message','code','request_id']);
test_domain_conflict_maps_to_409: assertStatus(409)->assertJsonPath('code','conflict');
test_model_not_found_hides_model_class: assertNotFound(); assertStringNotContainsString('App\\Models', content);
test_unauthenticated_envelope: route behind auth:sanctum → 401 code 'unauthenticated';
test_forbidden_envelope: route throws AuthorizationException → 403 code 'forbidden';
test_invalid_sort_returns_400: route QueryBuilder::for(User::class)->allowedSorts('id'); getJson('?sort=password')->assertStatus(400)->assertJsonPath('code','bad_request');
test_unknown_filter_returns_400: '?filter[password]=x' → 400 bad_request;
test_throttle_envelope: 429 with code 'too_many_requests' and Retry-After header;
// HealthTest
test_health_ok: getJson('/api/v1/health')->assertOk()->assertExactJson(['data' => ['status' => 'ok']]);
// RequestIdTest
test_generates_uuid: header X-Request-Id matches UUID regex;
test_echoes_valid_incoming_uuid / test_replaces_invalid_incoming_value ('<script>' → new uuid);
// LocaleTest
test_accept_language_ru_selects_ru / test_unsupported_language_falls_back_to_uz (assert validation message equals __('validation.required', …, 'ru'|'uz'));
// SearchTermTest (unit, uses DB on users.name via factory)
test_apostrophe_variants_match: name "Gʻulomov" found by "G'ulomov" and "G‘ulomov"; name "O'rozov" found by "Oʻroz";
test_wildcards_are_literal: names ["100% ok", "1000 ok"] → term "100%" returns only "100% ok"; term "_" matches nothing without underscore;
// PaginationTest
perPage: none→25, '1000'→100, '0'→1, 'abc'→25, '40'→40.
```

- [ ] **Step 2: Run** `make test-backend ARGS="--filter='ErrorEnvelopeTest|HealthTest|RequestIdTest|LocaleTest|SearchTermTest|PaginationTest'"` → FAIL (classes/routes missing).
- [ ] **Step 3: Implement** the Produces list. Exception rendering in `bootstrap/app.php` `withExceptions`: for `api/*` or `expectsJson()`, map `ValidationException→422`, `AuthenticationException→401`, `AuthorizationException|AccessDeniedHttpException→403`, `ModelNotFoundException|NotFoundHttpException→404`, `DomainConflictException→409`, `TokenMismatchException|419→419`, `ThrottleRequestsException→429` (keep `Retry-After`), spatie `InvalidQuery`→400, other `HttpException`→its status with `bad_request` for 400, everything else → 500 `{message: __('messages.server_error'), code, request_id}`; messages from `lang/uz/messages.php`. `lang/uz/validation.php` is a full Uzbek translation of Laravel's validation file (attributes array included for Phase 1 fields).
- [ ] **Step 4: Run** same filter → PASS. Run `make lint` (Pint + Larastan) → clean.
- [ ] **Step 5: Commit** `feat(api): response envelope, error mapping, request id, locale, search and pagination helpers`.

---

### Task 3: Core schema, enums, models and factories

**Files:**
- Create migrations (timestamps `2026_11_03_0001xx`): `organizations`, `departments`, `employees`, `organization_it_responsibles`, `add_profile_fields_to_users` (username, organization_id, employee_id, locale, is_active, must_change_password, password_changed_at, last_login_at/ip, 2FA columns, created_by/updated_by, soft deletes; drop default `email` NOT NULL → nullable unique), `user_organization_access`, `system_settings`, `add_meta_to_roles` (display_name, description, is_system); publish Spatie permission migration + config (`models.role = App\Models\Role`); `php artisan make:notifications-table`
- Create: `app/Enums/{OrganizationType,EmployeeStatus}.php`, models listed in File Structure (except AuditLog), `app/Models/Concerns/HasUserStamps.php`, `app/Support/Permissions/Roles.php`, factories for every model
- Modify: `app/Models/User.php`, `AppServiceProvider` (`Relation::enforceMorphMap`, `Model::shouldBeStrict(! app()->isProduction())`)
- Test: `tests/Feature/Models/{OrganizationPathTest,UserStampsTest,SchemaConstraintsTest}.php`

**Interfaces:**
- Produces:
  - Columns exactly as `docs/DATABASE.md` §3.
  - `OrganizationType: head_office|subsidiary|branch|other`; `EmployeeStatus: active|on_leave|business_trip|dismissed` (both with `label(): string` via `__("enums.…")`).
  - Morph map: `organization, department, employee, organization_it_responsible, user, user_organization_access, system_setting, role`.
  - `Organization`: `parent()`, `children()`, `departments()`, `employees()`, `itResponsibles()`; `descendantsAndSelf(): Builder` (`path LIKE "{path}%"`); `created` hook sets `path = (parent.path ?? '/') . id . '/'`, `depth = parent ? parent.depth + 1 : 0` via `saveQuietly`.
  - `Employee`: `organization()`, `department()`, `user()` (hasOne), accessor `full_name` = "last first middle" (trimmed).
  - `User`: `HasApiTokens, HasRoles, Notifiable, SoftDeletes`; `organization()`, `employee()`, `organizationAccess()` (hasMany `UserOrganizationAccess`); `$hidden` password, remember_token, two_factor_*; casts `password: hashed`, `two_factor_secret|two_factor_recovery_codes: encrypted`, booleans/datetimes; `isSuperAdmin(): bool`.
  - `Roles` constants: `SUPER_ADMIN='super-admin'`, `CENTRAL_IT_HEAD`, `CENTRAL_IT_STAFF`, `ORG_IT_RESPONSIBLE`, `ORG_HEAD`, `EMPLOYEE`, `ACCOUNTANT`, `AUDITOR` (+ `ALL` list).
  - `HasUserStamps`: on `creating` sets `created_by`/`updated_by`, on `updating` sets `updated_by`, only when `Auth::hasUser()`.
  - Factories: `OrganizationFactory::childOf(Organization $parent)`, `EmployeeFactory::dismissed()`, `UserFactory::forEmployee(Employee $e)` (sets organization_id), `UserFactory::inactive()`, `UserFactory::mustChangePassword()`.

- [ ] **Step 1: Write failing tests.**

```php
// OrganizationPathTest
test_root_gets_path_and_depth: $root=Organization::factory()->create(); assertSame("/{$root->id}/", $root->fresh()->path); assertSame(0, $root->fresh()->depth);
test_child_and_grandchild_paths: "/{$root->id}/{$child->id}/", "/{$root->id}/{$child->id}/{$grand->id}/", depth 2;
test_descendants_and_self: $root->descendantsAndSelf()->pluck('id') equals [root, child, grand] (sibling root excluded);
// UserStampsTest
test_stamps_set_when_authenticated: actingAs($u); create department → created_by == updated_by == $u->id; actingAs($v); update → updated_by == $v->id, created_by unchanged;
test_stamps_null_without_auth;
// SchemaConstraintsTest
test_personnel_number_unique_per_organization: duplicate in same org → QueryException; same number in other org → ok;
test_username_unique; test_user_email_nullable_but_unique;
test_user_hidden_attributes: toArray() lacks password, remember_token, two_factor_secret.
```

- [ ] **Step 2: Run** `make test-backend ARGS="--filter='OrganizationPathTest|UserStampsTest|SchemaConstraintsTest'"` → FAIL.
- [ ] **Step 3: Implement** migrations, enums, models, factories per Interfaces; FK/cascade strategy per `docs/DATABASE.md` §1; indexes per §3.
- [ ] **Step 4: Run** → PASS; `docker compose exec -T backend php artisan migrate:fresh` → no errors; `make lint` clean.
- [ ] **Step 5: Commit** `feat(db): organizations, departments, employees, users profile, access and settings schema`.

---

### Task 4: Organization access scope (isolation core)

**Files:**
- Create: `app/Support/Access/{AccessScope,OrganizationAccessResolver}.php`, `app/Models/Scopes/OrganizationScope.php`, `app/Models/Concerns/BelongsToOrganization.php`, `app/Rules/AccessibleOrganization.php`, `tests/Concerns/InteractsWithAccess.php`
- Modify: `Organization` (column `id`), `Department`, `Employee`, `OrganizationItResponsible`, `User` (all `use BelongsToOrganization`); `User` gains `accessScope()`, `canAccessOrganization()`; `AppServiceProvider` (`scoped` binding of resolver)
- Test: `tests/Feature/Access/{OrganizationAccessResolverTest,OrganizationScopeTest,AccessibleOrganizationRuleTest}.php`

**Interfaces:**
- Produces:
  - `AccessScope`: `isUnrestricted(): bool`, `organizationIds(): array<int>`, `allows(?int $organizationId): bool` (null → only unrestricted), `coversSubtreeOf(Organization $org): bool` (unrestricted, or all ids of `$org->descendantsAndSelf()` are in the set)
  - `OrganizationAccessResolver::resolve(User $user): AccessScope` (memoised per user id for the request), `forget(User $user): void`
  - `User::accessScope(): AccessScope`, `User::canAccessOrganization(?int $id): bool`
  - `BelongsToOrganization`: registers `OrganizationScope`; `static organizationColumn(): string` (default `organization_id`; Organization overrides → `id`); `scopeWithinOrganizationSubtree(Builder $q, int $organizationId)` (intersection with subtree via join/`whereIn` on `organizations.path LIKE`)
  - `OrganizationScope::apply`: only if `Auth::hasUser()` and scope not unrestricted → `whereIn(column, ids)` (empty ids → `whereRaw('1=0')`)
  - `AccessibleOrganization(bool $mustBeActive = true)` validation rule (message `validation.accessible_organization`)
  - Test helper trait `InteractsWithAccess`: `seedRbac(): void` (runs `RbacSeeder`, available after Task 6 — until then creates roles inline), `makeUser(string $role, ?Organization $org = null, bool $descendants = false): User` (home org = $org, access row for $org)

- [ ] **Step 1: Write failing tests.**

```php
// OrganizationAccessResolverTest
test_super_admin_is_unrestricted;
test_access_with_descendants_includes_whole_subtree: tree R→A→A1, R→B; access(A, true) → ids == [A, A1];
test_access_without_descendants_is_exact: access(A, false) → [A];
test_future_child_is_covered_after_forget: create A2 under A; resolver->forget($u); ids contain A2;
test_no_access_rows_means_empty_scope: allows(A) false;
test_covers_subtree: access(A,true) coversSubtreeOf(A) true; access(A,false) with A1 existing → false;
// OrganizationScopeTest
test_subsidiary_user_sees_only_own_employees: 3 employees in A, 2 in B; actingAs(user access A) → Employee::count()==3, Employee::find($bEmployee->id)===null;
test_unauthenticated_context_is_unscoped: Employee::count()==5;
test_super_admin_sees_all;
test_users_without_organization_hidden_from_non_super_admin;
test_organization_model_scoped_by_id: Organization::pluck('id') == [A] for access(A,false);
// AccessibleOrganizationRuleTest
test_passes_for_accessible_active; test_fails_for_other_org; test_fails_for_inactive_when_must_be_active; test_passes_inactive_when_not_required.
```

- [ ] **Step 2: Run** `make test-backend ARGS="--filter=Access"` → FAIL.
- [ ] **Step 3: Implement** Produces list. The resolver queries access rows with their organizations once; for `include_descendants` rows, one query `organizations.path LIKE ?` per row (escape `%`/`_` not needed — paths are digits and `/`). Super admin check uses `Roles::SUPER_ADMIN` via `hasRole` (Spatie caches permissions).
- [ ] **Step 4: Run** → PASS; full suite green; `make lint` clean.
- [ ] **Step 5: Commit** `feat(access): organization access resolver, global scope and validation rule`.

---

### Task 5: Audit log

**Files:**
- Create: migration `audit_logs` (columns per DATABASE.md, `created_at` `timestamp(3)`), `app/Enums/AuditEvent.php`, `app/Models/AuditLog.php`, `app/Models/Concerns/Auditable.php`, `app/Services/Audit/AuditLogger.php`
- Modify: `Organization, Department, Employee, OrganizationItResponsible, User` (`use Auditable`)
- Test: `tests/Feature/Audit/{AuditableTest,AuditLogImmutabilityTest}.php`

**Interfaces:**
- Produces:
  - `AuditEvent` cases: `Created='created', Updated='updated', Deleted='deleted', Restored='restored', Login='login', Logout='logout', LoginFailed='login_failed', PasswordChanged='password_changed', PasswordReset='password_reset', RolesChanged='roles_changed', AccessChanged='access_changed', SettingsUpdated='settings_updated'`
  - `AuditLogger::record(AuditEvent $event, ?Model $subject = null, array $old = [], array $new = [], ?User $actor = null, ?int $organizationId = null, ?string $userLabel = null): AuditLog` — actor defaults to `Auth::user()`; org defaults to subject's `organizationColumn()` value; request context (ip, user agent ≤255, method, path ≤500, `request_id`) when running in HTTP
  - `Auditable`: hooks `created/updated/deleted/restored`; `updated` records only changed keys (old/new); global excludes `password, remember_token, two_factor_secret, two_factor_recovery_codes, created_at, updated_at, created_by, updated_by, deleted_at`; model may add `protected array $auditExclude = []`; no row if only excluded keys changed
  - `AuditLog`: no `updated_at`; `updating`/`deleting` throw `LogicException`; casts json; relation `user()` (withTrashed)

- [ ] **Step 1: Write failing tests.**

```php
// AuditableTest
test_create_is_audited: create employee as $actor → AuditLog where auditable_type 'employee' event 'created': user_id $actor->id, user_label $actor->username, organization_id == employee org, new_values['last_name'] set, no 'updated_at' key;
test_update_records_only_changes: update position → old_values == ['position'=>old], new_values == ['position'=>new];
test_delete_and_restore_are_audited;
test_password_change_never_logged: update user password + name → new_values has 'name', lacks 'password'; password-only change → no new row;
test_request_context_captured: via HTTP test route that updates a department with header X-Request-Id → row has ip_address, http_method 'POST', request_id equal header;
test_organization_subject_uses_own_id_as_organization;
// AuditLogImmutabilityTest
test_update_throws: expectException(LogicException) on $log->update([...]);
test_delete_throws.
```

- [ ] **Step 2: Run** `make test-backend ARGS="--filter=Audit"` → FAIL.
- [ ] **Step 3: Implement** Produces list. Audit rows are written inside the same DB transaction as the change (model events fire inside service transactions).
- [ ] **Step 4: Run** → PASS; full suite + lint green.
- [ ] **Step 5: Commit** `feat(audit): append-only audit log with model and request context`.

---

### Task 6: RBAC registry, default roles, seeder

**Files:**
- Create: `config/permissions.php`, `app/Support/Permissions/PermissionRegistry.php`, `database/seeders/RbacSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (calls `RbacSeeder`), `AppServiceProvider` (`Gate::before` → true for super admin), `tests/Concerns/InteractsWithAccess.php` (`seedRbac()` runs `RbacSeeder`)
- Test: `tests/Feature/Rbac/RbacSeederTest.php`, `tests/Unit/PermissionRegistryTest.php`

**Interfaces:**
- Produces:
  - `config/permissions.php`: `modules => ['dashboard'=>['view'], 'organizations'=>['view','create','update','delete','manage_it_staff'], 'departments'=>['view','create','update','delete'], 'employees'=>['view','create','update','delete'], 'users'=>['view','create','update','deactivate','reset_password','manage_access'], 'roles'=>['view','manage'], 'audit'=>['view'], 'settings'=>['view','update']]`; `roles => [name => ['display_name', 'description', 'permissions' => '*' | list]]` exactly the matrix in `docs/DATABASE.md` §4 (Uzbek display names from that section)
  - `PermissionRegistry::all(): list<string>`, `modules(): array<string, list<string>>` (full names), `defaultRoles(): array<string, array{display_name: string, description: string, permissions: list<string>}>` (`'*'` expanded)
  - `RbacSeeder`: creates missing permissions (guard `web`); new roles get their default permissions and `is_system=true`; existing roles only receive defaults that were **newly created in this run** (admin edits survive re-seeding); resets Spatie cache

- [ ] **Step 1: Write failing tests.**

```php
test_seeding_twice_is_idempotent: run twice → Permission::count() == count(registry all), Role::count() == 8;
test_matrix_applied: role('org-head') hasPermissionTo('employees.view') && !hasPermissionTo('employees.update'); role('central-it-staff') has employees.create, employees.update, not employees.delete;
test_super_admin_passes_any_gate: $u role super-admin → $u->can('anything.at_all') true;
test_admin_removal_survives_reseed: revoke 'audit.view' from auditor; reseed → still revoked;
test_new_permission_granted_to_default_roles: config(['permissions.modules.audit' => ['view','export'], 'permissions.roles.auditor.permissions' => [..., 'audit.export']]); reseed → auditor has 'audit.export';
// PermissionRegistryTest
test_all_lists_module_action_names ('employees.update' in all()); test_star_expands_to_all.
```

- [ ] **Step 2: Run** `make test-backend ARGS="--filter='RbacSeederTest|PermissionRegistryTest'"` → FAIL.
- [ ] **Step 3: Implement** Produces list.
- [ ] **Step 4: Run** → PASS; full suite + lint green.
- [ ] **Step 5: Commit** `feat(rbac): permission registry, default roles and idempotent seeder`.

---

### Task 7: System settings

**Files:**
- Create: `config/settings.php`, `app/Services/Settings/SettingsService.php`, `app/Http/Controllers/Api/V1/Settings/{SettingsController,PublicSettingsController}.php`, `app/Http/Requests/Settings/UpdateSettingsRequest.php`
- Modify: `routes/api.php` (`GET /settings/public` throttle `public`; authenticated group `auth:sanctum` + throttle `api`: `GET|PUT /settings`)
- Test: `tests/Feature/Settings/SettingsApiTest.php`

**Interfaces:**
- Produces:
  - `config/settings.php` keys: `general.system_name` (string, default `AT.urtg.uz`, public, `required|string|max:100`), `general.organization_name` (default `"Urganchtransgaz" MCHJ`, public, `required|string|max:255`), `general.support_phone` (null, public, `nullable|string|max:50`), `general.support_email` (null, public, `nullable|email|max:255`), `security.password_min_length` (10, `required|integer|min:8|max:64`), `security.password_expires_days` (0, `required|integer|min:0|max:365`)
  - `SettingsService::get(string $key): mixed`, `all(): array<string, mixed>`, `publicValues(): array`, `definitions(): array` (key → type, default, value, public), `update(array $values, User $actor): void` (tx; audit `settings_updated` with old/new; cache key `system_settings` forever, forgotten on update)
  - `GET /settings` → `{data: [{key, type, value, default, public}]}`; `PUT /settings` body `{values: {key: value}}`; `GET /settings/public` → `{data: {system_name, organization_name, support_phone, support_email}}`

- [ ] **Step 1: Write failing tests.**

```php
test_auditor_can_view_settings / test_employee_gets_403;
test_super_admin_updates_and_is_audited: PUT values {security.password_min_length: 12} → 200; SettingsService::get(...) === 12; AuditLog event 'settings_updated' old 10 new 12;
test_unknown_key_rejected_422 (errors key 'values.foo');
test_invalid_value_rejected_422: min_length 3 → errors 'values.security.password_min_length' (dotted key escaped by request);
test_central_it_head_cannot_update_403;
test_public_endpoint_unauthenticated_exposes_only_public_keys: assertJsonMissingPath('data.password_min_length'); has 'data.system_name'.
```

- [ ] **Step 2: Run** `--filter=SettingsApiTest` → FAIL. **Step 3: Implement.** Authorization via `Gate`/`authorize('settings.view'|'settings.update')`. **Step 4: Run** → PASS; suite + lint green.
- [ ] **Step 5: Commit** `feat(settings): typed system settings with public subset and audit`.

---

### Task 8: Authentication

**Files:**
- Create: `app/Http/Controllers/Api/V1/Auth/{LoginController,MeController,PasswordController,ForgotPasswordController,ResetPasswordController}.php`, requests `app/Http/Requests/Auth/{LoginRequest,UpdateMeRequest,ChangePasswordRequest,ForgotPasswordRequest,ResetPasswordRequest}.php`, `app/Http/Resources/Auth/MeResource.php`, `app/Http/Middleware/{EnsureUserIsActive,EnsurePasswordIsChanged}.php`, `app/Services/Auth/{SessionRevoker,PasswordRules}.php`, `app/Notifications/ResetPasswordLink.php`, `app/Console/Commands/CreateSuperAdmin.php`
- Modify: `routes/api.php` (auth routes; authenticated group gets `EnsureUserIsActive`, `EnsurePasswordIsChanged`), `AppServiceProvider` (`login` limiter: `Limit::perMinute(5)->by(lower(username).'|'.ip)` + `Limit::perMinute(30)->by(ip)`; `ResetPassword::createUrlUsing` → `{APP_URL}/reset-password?token=…&email=…`), `User::sendPasswordResetNotification`
- Test: `tests/Feature/Auth/{LoginTest,AccountStateTest,PasswordChangeTest,PasswordResetTest,MeTest,CreateSuperAdminCommandTest}.php`

**Interfaces:**
- Produces:
  - Routes (names): `POST /auth/login` (`auth.login`), `POST /auth/logout` (`auth.logout`), `GET|PATCH /auth/me` (`auth.me`, `auth.me.update`), `PUT /auth/password` (`auth.password.update`), `POST /auth/forgot-password`, `POST /auth/reset-password`
  - `MeResource`: `{id, username, name, email, locale, must_change_password, password_expired, is_super_admin, organization: {id,name}|null, employee: {id, full_name, position}|null, roles: [{name, display_name}], permissions: string[] (super admin → registry all), access: [{organization_id, organization_name, include_descendants}]}`
  - `SessionRevoker::revokeAll(User $u): void` (sessions rows + Sanctum tokens), `revokeOthers(User $u, string $currentSessionId): void`
  - `PasswordRules::make(): Password` (min from `security.password_min_length`, `letters()`, `numbers()`)
  - `User::passwordExpired(): bool` (days setting > 0 and `password_changed_at` older or null)
  - Middleware: `EnsureUserIsActive` → logout + 401 `unauthenticated` if `!is_active`; `EnsurePasswordIsChanged` → 403 `password_change_required` unless route is `auth.me`, `auth.me.update`, `auth.logout`, `auth.password.update`
  - Command `app:create-super-admin {--from-env} {--username=} {--name=} {--email=}`: interactive password prompt (confirmed, `PasswordRules`) or env `SUPER_ADMIN_*`; runs `RbacSeeder` if role missing; existing username → ensure role, password untouched, exit 0; new → `must_change_password=true`, `organization_id=null`

- [ ] **Step 1: Write failing tests** (stateful requests send `Referer: http://localhost`; `SANCTUM_STATEFUL_DOMAINS` includes `localhost`).

```php
// LoginTest
test_login_success_returns_me_and_audits: postJson('/api/v1/auth/login', [username, password]) → 200 data.username; AuditLog event 'login'; user last_login_at set;
test_wrong_password_422_generic_and_audited: errors.username == [__('auth.failed')]; AuditLog 'login_failed' user_label == attempted username; json_encode(row) lacks the password;
test_unknown_username_same_response_as_wrong_password;
test_sixth_attempt_throttled: 5×wrong → 6th 429 'too_many_requests';
test_logout_invalidates_session_and_audits: after logout getJson('/api/v1/auth/me') → 401;
// AccountStateTest
test_inactive_user_cannot_login: 422 errors.username == [__('auth.inactive')];
test_deactivated_user_gets_401_on_next_request: actingAs($u); $u->update(['is_active'=>false]); getJson('/api/v1/auth/me') → 401;
test_must_change_password_gate: actingAs(mustChangePassword) → GET /settings 403 code 'password_change_required'; GET /auth/me 200; POST /auth/logout 200;
test_expired_password_forces_change: setting password_expires_days=30, password_changed_at = now()-31d → 403 password_change_required; me.password_expired true;
// PasswordChangeTest
test_requires_correct_current_password (422 errors.current_password);
test_respects_min_length_setting: setting 12 → 'Abc123def45' (11) rejected, 'Abc123def456' accepted;
test_requires_letters_and_numbers;
test_success_clears_flag_sets_changed_at_revokes_other_sessions_and_audits: insert 2 sessions rows for user (one = current) → after: only current remains; must_change_password false; AuditLog 'password_changed';
// PasswordResetTest
test_forgot_password_same_response_for_unknown_and_known: Notification::fake(); both 200 same message; ResetPasswordLink sent only to known user with email;
test_user_without_email_gets_no_mail;
test_reset_with_valid_token: Password::createToken($u) → POST reset → login with new password works; must_change_password false; sessions revoked; AuditLog 'password_reset';
test_reset_link_points_to_spa: notification toMail action URL starts with config('app.url').'/reset-password?token=';
// MeTest
test_me_lists_roles_permissions_access: org-it-responsible with access(A) → permissions contains 'employees.update', access[0].organization_id == A;
test_super_admin_me_has_all_permissions;
test_patch_me_locale: 'ru' ok; 'en' 422;
// CreateSuperAdminCommandTest
test_from_env_creates_super_admin_once: config env via putenv/$_ENV SUPER_ADMIN_*; artisan twice → exit 0, one user, hasRole super-admin, must_change_password true;
test_from_env_fails_without_password: exit code 1, no user;
test_weak_password_rejected.
```

- [ ] **Step 2: Run** `--filter=Auth` → FAIL. **Step 3: Implement** Produces list; login uses `Auth::guard('web')->attempt`-equivalent with explicit active check, `session()->regenerate()`, `updateQuietly` for last-login fields; `lang/uz/auth.php` gets `failed`, `inactive`, `throttle`; `lang/uz/passwords.php` Uzbek. **Step 4: Run** → PASS; suite + lint green.
- [ ] **Step 5: Commit** `feat(auth): SPA login, password policy, forced change, reset, session revocation, super admin CLI`.

---

### Task 9: Organizations module

**Files:**
- Create: `app/Http/Controllers/Api/V1/Organizations/{OrganizationController,OrganizationTreeController,ItResponsibleController}.php`, `app/Http/Requests/Organizations/{StoreOrganizationRequest,UpdateOrganizationRequest,StoreItResponsibleRequest}.php`, `app/Http/Resources/Organizations/{OrganizationResource,OrganizationTreeNodeResource,ItResponsibleResource}.php`, `app/Policies/OrganizationPolicy.php`, `app/Services/Organizations/OrganizationService.php`, `app/Support/QueryFilters/OrganizationSubtreeFilter.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Organizations/{OrganizationApiTest,OrganizationMoveTest,ItResponsibleApiTest}.php`

**Interfaces:**
- Consumes: `AccessScope`, `AccessibleOrganization`, `AuditLogger`, `ApiResponse`, `Pagination`, `SearchTerm`, `DomainConflictException`
- Produces:
  - `OrganizationService::create(array $data): Organization`, `update(Organization $o, array $data): Organization` (parent change → `move`), `move(Organization $o, ?Organization $newParent): void`, `delete(Organization $o): void`, `assignItResponsible(Organization $o, Employee $e, bool $isPrimary, string $assignedAt, ?string $notes): OrganizationItResponsible`, `removeItResponsible(Organization $o, Employee $e): void`
  - `OrganizationSubtreeFilter` — spatie custom filter for `filter[organization]=<id>`: restrict to that org's subtree (inaccessible ids simply yield nothing). Reused by every later list.
  - `OrganizationResource` fields: `id, parent_id, parent{id,name}, depth, type, type_label, name, short_name, code, tin, address, phone, email, head_name, is_active, sort_order, children_count, created_at, updated_at, can{update,delete,manage_it_staff}`
  - `GET /organizations/tree` → `{data: [{id, name, short_name, type, is_active, children: [...]}]}`; nodes whose parent is out of scope become roots
  - List: filters `type, is_active, parent, organization`; search `name, short_name, code, tin`; sorts `name, code, sort_order, created_at`; default `sort_order,name`

- [ ] **Step 1: Write failing tests.**

```php
// OrganizationApiTest  (tree: R → A, R → B; users: head=central-it-head access(R,true), subA=org-it-responsible access(A))
test_central_head_creates_child: POST {parent_id:R, type:'subsidiary', name, code:'GTS-1'} → 201, data.depth 1, path "/R/{id}/" in DB, AuditLog created;
test_only_unrestricted_can_create_root: head POST parent_id null → 422 errors.parent_id; super admin → 201;
test_subsidiary_user_cannot_create: subA POST → 403;
test_isolation_show_update_delete: subA GET/PUT/DELETE /organizations/B → 404 each;
test_list_and_tree_only_show_scope: subA list ids == [A]; tree == [A as root];
test_validation: tin '12345' → 422; duplicate code → 422; parent outside scope → 422;
test_delete_with_children_409 / test_delete_empty_soft_deletes (200, deleted_at set);
test_list_params: ?sort=password → 400; ?per_page=1000 → meta.per_page 100; ?per_page=abc → 25;
test_filter_organization_out_of_scope_returns_empty: subA ?filter[organization]=B → data [];
test_can_flags: head sees can.update true; org-head (access A) sees can.update false;
// OrganizationMoveTest
test_move_rewrites_subtree_paths_and_depth: R→A→A1→A1a; move A1 under B → A1.path "/R/B/A1/", A1a.path "/R/B/A1/A1a/", depth 2 and 3;
test_cannot_move_into_own_descendant: PUT A {parent_id: A1} → 422 errors.parent_id;
test_move_requires_access_to_whole_subtree_and_target;
test_visibility_follows_move (Review Focus 4): uA access(A,true), uB access(B,true); move A1 under B → uA GET A1 → 404; uB GET A1 → 200;
// ItResponsibleApiTest
test_assign_and_list: head POST {employee_id, is_primary:true, assigned_at} → 201; GET lists it with employee full_name;
test_only_one_primary: assign second primary → first becomes is_primary false;
test_duplicate_assignment_422; test_remove; test_subsidiary_user_403;
test_employee_out_of_actor_scope_422.
```

- [ ] **Step 2: Run** `--filter=Organization` → FAIL.
- [ ] **Step 3: Implement.** Move algorithm inside `DB::transaction` with `lockForUpdate` on the moved row:

```sql
UPDATE organizations
SET path  = CONCAT(:newPrefix, SUBSTRING(path, CHAR_LENGTH(:oldPrefix) + 1)),
    depth = depth + :delta
WHERE path LIKE CONCAT(:oldPrefix, '%')
-- oldPrefix = moved.path; newPrefix = (newParent.path ?? '/') . moved.id . '/'; delta = newDepth - moved.depth
```

  Policy methods: `viewAny/view` → `organizations.view`; `create` → `organizations.create`; `update/delete` → permission + `canAccessOrganization($o->id)`; `manageItStaff` → `organizations.manage_it_staff` + access. Delete guard: non-deleted children, departments or employees → `DomainConflictException(__('messages.organization_not_empty'))`.
- [ ] **Step 4: Run** → PASS; suite + lint green.
- [ ] **Step 5: Commit** `feat(organizations): tree CRUD with subtree move, IT responsibles, scoped listing`.

---

### Task 10: Departments and employees

**Files:**
- Create: controllers `Departments/DepartmentController.php`, `Employees/EmployeeController.php`; requests `Departments/{Store,Update}DepartmentRequest.php`, `Employees/{Store,Update}EmployeeRequest.php`; resources `DepartmentResource`, `EmployeeResource`; policies `DepartmentPolicy`, `EmployeePolicy`; services `Departments/DepartmentService.php`, `Employees/EmployeeService.php`; events `EmployeeDismissed`, `EmployeeTransferred`; listeners `DeactivateLinkedUser`, `SyncLinkedUserOrganization`
- Modify: `routes/api.php`
- Test: `tests/Feature/Departments/DepartmentApiTest.php`, `tests/Feature/Employees/{EmployeeApiTest,EmployeeLifecycleTest}.php`

**Interfaces:**
- Consumes: Task 4 scope/rule, `SessionRevoker` (Task 8), `AuditLogger`, `OrganizationSubtreeFilter`, `SearchTerm`
- Produces:
  - `DepartmentService::create(array): Department`, `update(Department, array): Department` (organization_id immutable — `prohibited` on update), `delete(Department): void` (409 if employees or child departments)
  - `EmployeeService::create(array): Employee`, `update(Employee, array): Employee` (fires `EmployeeDismissed` when status becomes `dismissed`; `EmployeeTransferred($employee, int $fromOrganizationId)` when org changes), `delete(Employee): void` (409 if linked user exists; removes IT-responsible rows)
  - `EmployeeResource`: `id, organization{id,name}, department{id,name}|null, last_name, first_name, middle_name, full_name, position, personnel_number, phone, internal_phone, email, status, status_label, hired_at, dismissed_at, notes, user{id,username,is_active}|null, created_at, updated_at, can{update,delete}`
  - Employee list: filters `organization, department, status`; search `last_name, first_name, middle_name, personnel_number, phone` (via `SearchTerm`); sorts `last_name, personnel_number, status, created_at`; default `last_name`
  - Validation highlights: `department_id` must belong to `organization_id`; `personnel_number` unique per org **including soft-deleted rows** (ignore self); `dismissed_at` `required_if:status,dismissed`; `phone` regex `/^\+?[0-9\s\-()]{5,20}$/`; changing `organization_id` requires access to the new org (old one already enforced by scope)

- [ ] **Step 1: Write failing tests.**

```php
// DepartmentApiTest
test_org_it_responsible_crud_in_own_org; test_isolation_404_for_other_org_department;
test_parent_from_other_org_422; test_code_unique_per_org_422; test_delete_with_employees_409;
test_organization_change_prohibited_422;
// EmployeeApiTest
test_central_staff_can_create_and_update_but_not_delete (delete → 403);
test_org_it_responsible_full_crud_own_org;
test_create_in_inaccessible_org_422 (errors.organization_id);
test_department_from_other_org_422;
test_isolation_404_show_update_delete;
test_filters_status_and_department;
test_search_apostrophe_variants (Review Focus 1): employee last_name 'Gʻulomov' found by search "G'ulomov"; 'O'rozov' found by "Oʻroz";
test_search_wildcards_literal: search '%' returns only names containing '%' (none) → [];
test_personnel_number_unique_including_soft_deleted_422;
test_dismissed_requires_dismissed_at_422;
// EmployeeLifecycleTest
test_dismissal_deactivates_linked_user_and_revokes_sessions: linked user + sessions row → PUT status dismissed → user is_active false, sessions for user 0;
test_transfer_moves_user_home_org_and_access (Review Focus 3): user home A with access(A,false); PUT organization_id B (actor head) → user.organization_id == B; access rows == [(B,false)]; AuditLog 'access_changed'; descendant rows (A,true) granted by admin are kept;
test_delete_with_linked_user_409.
```

- [ ] **Step 2: Run** `--filter='Department|Employee'` → FAIL. **Step 3: Implement** (listeners registered via `Event::listen` auto-discovery). **Step 4: Run** → PASS; suite + lint green.
- [ ] **Step 5: Commit** `feat(people): departments and employees with dismissal and transfer side effects`.

---

### Task 11: Users, roles, notifications base

**Files:**
- Create: controllers `Users/{UserController,UserStatusController,UserPasswordResetController,UserRolesController,UserOrganizationAccessController}.php`, `Roles/{RoleController,PermissionController}.php`, `Notifications/NotificationController.php`; requests `Users/{StoreUserRequest,UpdateUserRequest,SyncRolesRequest,SyncOrganizationAccessRequest}.php`, `Roles/{StoreRoleRequest,UpdateRoleRequest}.php`; resources `UserResource`, `RoleResource`, `NotificationResource`; policies `UserPolicy`, `RolePolicy`; services `Users/{UserService,AccessManager,TemporaryPassword}.php`; notifications `AccessChanged`, `PasswordResetByAdmin` (both `ShouldQueue`, `via → ['database']`)
- Modify: `routes/api.php`, `lang/uz/messages.php`
- Test: `tests/Feature/Users/{UserApiTest,AccessManagerTest,UserStatusTest,AdminPasswordResetTest}.php`, `tests/Feature/Roles/RoleApiTest.php`, `tests/Feature/Notifications/NotificationApiTest.php`

**Interfaces:**
- Consumes: `SessionRevoker`, `OrganizationAccessResolver::forget`, `AccessScope::coversSubtreeOf`, `PermissionRegistry`, `AuditLogger`
- Produces:
  - `TemporaryPassword::generate(): string` (14 chars, ≥1 letter, ≥1 digit, no ambiguous `0O1lI`)
  - `UserService::create(array $data, User $actor): array{user: User, temporary_password: string}` (tx; default access = home org non-descendant; `must_change_password=true`), `update(User, array, User $actor): User`, `deactivate(User $target, User $actor): void`, `activate(User, User): void`, `resetPassword(User $target, User $actor): string`
  - `AccessManager::assertCanManage(User $target, User $actor): void` (403 if target is self for role/access/status changes, or target is super admin and actor is not), `syncRoles(User $target, array $roleNames, User $actor): void`, `syncOrganizationAccess(User $target, array $access, User $actor): void` (`$access`: list of `{organization_id:int, include_descendants:bool}`), `canAssignRole(Role $role, User $actor): bool` (super admin → true; role super-admin → false; else role permissions ⊆ actor permissions); last active super admin protected → `DomainConflictException`
  - `POST /users` 201 → `{data: UserResource, meta: {temporary_password}}`; `POST /users/{id}/reset-password` → `{data: {temporary_password}}`
  - `UserResource`: `id, username, name, email, locale, is_active, must_change_password, last_login_at, organization{id,name}|null, employee{id,full_name}|null, roles[{name,display_name}], access[{organization_id, organization_name, include_descendants}], created_at, can{update, deactivate, reset_password, manage_access}`
  - `RoleResource`: `id, name, display_name, description, is_system, permissions: string[], users_count`
  - `GET /permissions` → `{data: [{module, permissions: string[]}]}`
  - Notification payload (`toArray`): `{type: 'access_changed'|'password_reset_by_admin', title_key, params, url}`; `GET /notifications` (filter `unread`), `GET /notifications/unread-count` → `{data: {count}}`, `POST /notifications/{id}/read`, `POST /notifications/read-all`

- [ ] **Step 1: Write failing tests.**

```php
// UserApiTest
test_create_returns_temporary_password_once: 201 meta.temporary_password (14 chars); GET /users/{id} lacks it; user must_change_password true; access == [(home,false)];
test_temporary_password_can_log_in_and_is_forced_to_change;
test_username_format_and_unique_including_soft_deleted (422);
test_employee_already_linked_422; test_employee_org_must_match_organization_id_422;
test_non_super_admin_must_set_organization_422;
test_list_scoped: head (access R,true) sees users of A and B, not null-org super admin; super admin sees all;
test_org_head_without_users_view_403;
// AccessManagerTest
test_head_cannot_grant_super_admin_403;
test_head_cannot_grant_role_with_permissions_he_lacks_403 (custom role with roles.manage);
test_head_can_grant_org_it_responsible_and_target_notified (Notification::fake → AccessChanged);
test_access_outside_actor_scope_422 (errors 'access.0.organization_id');
test_include_descendants_requires_subtree_cover_422;
test_cannot_change_own_roles_or_access_403;
test_last_active_super_admin_cannot_lose_role_409;
test_roles_change_audited_roles_changed_with_old_new;
test_scope_refreshes_after_access_change (resolver forget): target immediately sees new org within same test;
// UserStatusTest
test_deactivate_revokes_sessions_and_blocks_api; test_cannot_deactivate_self_403; test_cannot_deactivate_last_super_admin_409; test_activate;
// AdminPasswordResetTest
test_reset_returns_temp_password_sets_flag_revokes_sessions_and_notifies (PasswordResetByAdmin);
test_non_super_admin_cannot_reset_super_admin_403;
// RoleApiTest
test_super_admin_creates_role_with_permissions; test_unknown_permission_422; test_name_slug_format_422;
test_delete_system_role_409; test_delete_role_with_users_409; test_head_cannot_manage_403; test_auditor_can_view;
// NotificationApiTest
test_lists_only_own; test_unread_filter_and_count; test_mark_read; test_read_all; test_other_users_notification_404.
```

- [ ] **Step 2: Run** `--filter='User|Role|Notification|AccessManager'` → FAIL. **Step 3: Implement.** **Step 4: Run** → PASS; suite + lint green.
- [ ] **Step 5: Commit** `feat(users): user lifecycle, role and access management with escalation guards, notifications`.

---

### Task 12: Audit API, dashboard, search, API docs, demo data

**Files:**
- Create: controllers `Audit/AuditLogController.php`, `Dashboard/DashboardController.php`, `Search/SearchController.php`; `app/Http/Resources/Audit/AuditLogResource.php`; `app/Services/Dashboard/DashboardService.php`, `app/Services/Search/GlobalSearch.php`; `database/seeders/DemoSeeder.php`; publish `config/scramble.php`
- Modify: `routes/api.php`, `DatabaseSeeder` (`RbacSeeder` always; `DemoSeeder` only when `! app()->isProduction()`), `AppServiceProvider` (`Gate::define('viewApiDocs', fn(?User $u) => app()->isLocal() || $u?->isSuperAdmin())`)
- Test: `tests/Feature/Audit/AuditLogApiTest.php`, `tests/Feature/Dashboard/DashboardApiTest.php`, `tests/Feature/Search/SearchApiTest.php`, `tests/Feature/Seeders/DemoSeederTest.php`, `tests/Feature/Docs/ApiDocsTest.php`

**Interfaces:**
- Produces:
  - Audit list visibility: unrestricted → all; otherwise `organization_id IN scope ids` (null-org rows hidden). Filters `event, user, auditable_type, auditable_id, organization, created_from, created_to` (dates are Asia/Tashkent days converted to UTC bounds); sort `created_at` only, default `-created_at`. `AuditLogResource`: `id, event, event_label, auditable_type, auditable_id, user{id,username}|null, user_label, organization{id,name}|null, old_values, new_values, ip_address, user_agent, http_method, url, request_id, created_at`
  - `DashboardService::forUser(User $u, ?int $organizationId): array` → keys present only when permitted: `profile` (always: `{name, organization, roles}`), `organizations {total, active}` (organizations.view), `employees {total, by_status: {active, on_leave, business_trip, dismissed}}` (employees.view), `it_coverage {with_responsible, without_responsible}` (organizations.view), `users {total, active, pending_password_change}` (users.view), `recent_audit` (10 × AuditLogResource, audit.view). `organization_id` validated by `AccessibleOrganization(false)`; applies subtree filter.
  - `GlobalSearch::search(User $u, string $q): array{organizations?: list, employees?: list, users?: list}` — 5 each, group present only with its `*.view` permission; `q` `required|string|min:2|max:100`
  - `DemoSeeder`: aborts with a clear console error when `DEMO_PASSWORD` empty; idempotent via `firstOrCreate` on `code`/`username`; root `Markaziy apparat (demo)` (`DEMO-HQ`), 3 subsidiaries with `(demo)` suffix, 2–3 departments each, ~30 employees, one user per role (`demo.<role>`), access per DATABASE.md semantics (central roles → root with descendants)

- [ ] **Step 1: Write failing tests.**

```php
// AuditLogApiTest
test_auditor_with_root_access_lists_logs; test_employee_403;
test_scoped_auditor_sees_only_own_org_logs (and not null-org rows);
test_tashkent_day_boundary (Review Focus 2): log created_at '2026-11-02 19:30:00' UTC → filter[created_from]=2026-11-03 includes it; filter[created_to]=2026-11-02 excludes it;
test_filter_by_auditable; test_sort_by_other_column_400;
// DashboardApiTest
test_head_gets_all_widgets_with_correct_counts (factories: 2 orgs active + 1 inactive, employees by status);
test_employee_role_gets_profile_only (assertJsonMissingPath('data.employees'));
test_organization_param_out_of_scope_422; test_organization_param_filters_to_subtree;
// SearchApiTest
test_results_respect_scope_and_permissions: subA finds own employee, not B's; org-head gets no 'users' group;
test_q_too_short_422; test_apostrophe_variant_matches;
// DemoSeederTest
test_idempotent: run twice → same counts of organizations/users; test_skipped_in_production (app()->detectEnvironment(fn()=>'production'); db:seed → no demo users); test_requires_demo_password;
// ApiDocsTest
test_docs_available_locally (GET /docs/api 200); test_docs_forbidden_in_production_for_non_super_admin (403).
```

- [ ] **Step 2: Run** `--filter='AuditLogApi|Dashboard|Search|DemoSeeder|ApiDocs'` → FAIL. **Step 3: Implement.** **Step 4: Run** → PASS; full suite + lint green; `php artisan scramble:export` produces valid JSON.
- [ ] **Step 5: Commit** `feat: audit log API, dashboard metrics, global search, OpenAPI docs, demo seeder`.

---

### Task 13: Frontend foundation and authentication screens

Load the `frontend-design` and `senior-frontend` skills before writing UI code; record the chosen visual direction (palette, type scale, density) as a comment block at the top of `src/app/theme.ts`.

**Files:**
- Modify: `frontend/package.json`, `vite.config.ts` (Vitest: `environment: 'jsdom'`, `setupFiles: './src/test/setup.ts'`), `tsconfig*.json` (`strict`, `noUncheckedIndexedAccess`), `eslint.config.js` (typescript-eslint, react-hooks, jsx-a11y), `.prettierrc`, `index.html` (`lang="uz"`, title from project name)
- Create: `src/app/{App,providers,router,theme,queryClient}.tsx|ts`, `src/api/{client,errors,types}.ts`, `src/i18n/index.ts`, `src/i18n/locales/{uz,ru}/{common,auth,validation}.json`, `src/lib/{dayjs,format,forms}.ts`, `src/layouts/AuthLayout.tsx`, `src/features/auth/{api,queries,types,schemas}.ts`, `src/features/auth/{LoginPage,ForgotPasswordPage,ResetPasswordPage,ChangePasswordPage,RequireAuth,RequirePasswordChanged}.tsx`, `src/test/{setup,server,utils}.ts(x)`
- Test: `src/api/client.test.ts`, `src/lib/format.test.ts`, `src/features/auth/LoginPage.test.tsx`, `src/features/auth/RequirePasswordChanged.test.tsx`

**Interfaces:**
- Produces:
  - `api` (Axios, `baseURL '/api/v1'`, `withCredentials`, `withXSRFToken`, `Accept-Language` = i18n language); `ensureCsrfCookie(): Promise<void>` (`GET /sanctum/csrf-cookie`); interceptors: 419 → `ensureCsrfCookie()` then retry once (flag `_csrfRetried`); 401 → `onUnauthenticated()` callback (clears `['auth','me']`, navigates `/login?redirect=`); 403 `password_change_required` → navigate `/change-password`
  - `class ApiError extends Error { status: number; code: string; fieldErrors: Record<string, string[]>; requestId?: string }`; `toApiError(e: unknown): ApiError`
  - `type Paginated<T> = { data: T[]; meta: { current_page: number; per_page: number; total: number; last_page: number; from: number | null; to: number | null } }`; `type Resource<T> = { data: T }`
  - `applyApiErrors<T>(error: unknown, setError: UseFormSetError<T>): boolean` (maps 422 field errors; returns whether any applied)
  - `formatDate(iso: string | null): string` (`DD.MM.YYYY`), `formatDateTime(iso): string` (`DD.MM.YYYY HH:mm`), both in `Asia/Tashkent`, `'—'` for null; `formatNumber(n)`, `formatMoney(value: string | number): string` (`1 250 000,00 so'm`)
  - `type Me` mirrors `MeResource`; `useMe()` (query key `['auth','me']`, `staleTime 5 min`, 401 → `null`), `useLogin()`, `useLogout()`, `useChangePassword()`
  - Routes: `/login`, `/forgot-password`, `/reset-password`, `/change-password` (auth layout); everything else under `RequireAuth` → `RequirePasswordChanged` → app layout (Task 14)

- [ ] **Step 1: Install deps** (`@mantine/{core,hooks,dates,notifications,modals,spotlight}@^9`, `mantine-datatable@^9`, `@tabler/icons-react`, `react-router@^8`, `@tanstack/react-query@^5`, `axios`, `zod@^4`, `react-hook-form@^7`, `@hookform/resolvers`, `i18next`, `react-i18next`, `dayjs`, `zustand`, `@fontsource-variable/inter`; dev: `vitest`, `jsdom`, `@testing-library/{react,user-event,jest-dom}`, `msw@^2`, `typescript@~6.0`, `typescript-eslint`, `eslint-plugin-{react-hooks,jsx-a11y}`, `prettier`). Verify current Mantine 9 / React Router 8 APIs from installed type definitions before use.
- [ ] **Step 2: Write failing tests.**

```ts
// client.test.ts (msw)
it('retries once after 419 with a fresh CSRF cookie'): first POST → 419, csrf-cookie hit once, second POST → 200; resolves 200
it('does not loop on repeated 419'): rejects ApiError status 419 after exactly 2 POSTs
it('normalises 422 into fieldErrors'): toApiError(e).fieldErrors.username[0] === 'Bu login band.'
it('calls onUnauthenticated on 401')
// format.test.ts
expect(formatDateTime('2026-11-02T19:30:00Z')).toBe('03.11.2026 00:30'); expect(formatDate(null)).toBe('—');
expect(formatMoney('1250000.00')).toBe("1 250 000,00 so'm")   // NBSP-normalised in assertion
// LoginPage.test.tsx
it('shows required errors in Uzbek on empty submit'); it('shows server credential error under username');
it('navigates to redirect target after success')
// RequirePasswordChanged.test.tsx
it('redirects to /change-password when me.must_change_password')
```

- [ ] **Step 3: Run** `make test-frontend` → FAIL. **Step 4: Implement** Produces list; login page shows `general.system_name`, `general.organization_name`, support contacts from `GET /settings/public`. **Step 5: Run** `make test-frontend && docker compose exec -T frontend sh -c 'npm run typecheck && npm run lint && npm run build'` → PASS.
- [ ] **Step 6: Commit** `feat(frontend): app foundation, API client, i18n, auth screens`.

---

### Task 14: App shell and shared components

**Files:**
- Create: `src/layouts/AppLayout/{AppLayout,Sidebar,Header,AppBreadcrumbs,OrgContextSwitcher,NotificationsMenu,UserMenu,GlobalSearch,navigation}.tsx|ts`, `src/components/DataTable/{DataTable.tsx,index.ts}`, `src/components/{PageHeader,EmptyState,ErrorState,StatusBadge,PermissionGate,OrganizationSelect}.tsx`, `src/components/confirm.ts`, `src/hooks/{usePermissions,useTableParams}.ts`, `src/stores/ui.ts`, `src/features/errors/{ForbiddenPage,NotFoundPage}.tsx`, `src/features/notifications/{api,queries}.ts`, `src/features/search/api.ts`, `src/i18n/locales/{uz,ru}/{nav,table}.json`
- Test: `navigation.test.ts`, `PermissionGate.test.tsx`, `DataTable.test.tsx`, `useTableParams.test.tsx`

**Interfaces:**
- Produces:
  - `useCan(permission: string | string[], mode?: 'all' | 'any'): boolean` (super admin → true); `<PermissionGate permission fallback?>`
  - `navigation: NavItem[]` with `{key, labelKey, path, icon, permission?}` for dashboard, organizations, departments, employees, users, roles, audit, settings; `visibleNav(me: Me): NavItem[]`
  - `useTableParams({defaultSort, filterKeys})` → `{page, perPage, sort, search, filters, setPage, setPerPage, setSort, setSearch, setFilter, apiParams}`; state lives in URL search params; `apiParams` serialises to `page, per_page, sort ('-name'), search, filter[x]`
  - `<DataTable<T>>` props: `records?: T[]`, `columns`, `meta?`, `isLoading`, `error?: unknown`, `onRetry`, `params` (from `useTableParams`), `emptyTitleKey`, `emptyDescriptionKey`, `emptyAction?`, `storageKey` (column visibility persisted in localStorage with try/catch), `onRowClick?`
  - `confirmAction({titleKey, messageKey, confirmLabelKey, danger?}): Promise<boolean>`
  - `StatusBadge` colour map: active/green, on_leave/yellow, business_trip/blue, dismissed/gray, inactive/red, must_change_password/orange
  - `useUiStore`: `{sidebarCollapsed, toggleSidebar, orgContextId: number | null, setOrgContext}` (persisted, guarded storage)
  - Header: breadcrumbs from route `handle.crumb`, Spotlight search hitting `/search` (debounced 300 ms, min 2 chars), notifications menu (unread count polled every 60 s, mark read/all), org context switcher (options from `me.access`, "Barcha tashkilotlar" = null), user menu (profile, colour scheme toggle, logout)
  - Mobile (< `sm`): sidebar becomes a drawer toggled by burger

- [ ] **Step 1: Write failing tests.**

```ts
it('visibleNav hides items without permission')  // employee role → only dashboard
it('PermissionGate renders fallback when permission missing')
it('DataTable shows skeleton while loading, empty state when no records, error state with working retry')
it('useTableParams reads ?page=2&sort=-name&filter[status]=active and builds apiParams')
```

- [ ] **Step 2: Run** → FAIL. **Step 3: Implement.** **Step 4: Run** tests + typecheck + lint + build → PASS.
- [ ] **Step 5: Commit** `feat(frontend): app shell, navigation, search, notifications, shared table and states`.

---

### Task 15: Dashboard and organizations pages

**Files:**
- Create: `src/features/dashboard/{api.ts,DashboardPage.tsx,widgets/*.tsx}`, `src/features/organizations/{api,queries,types,schemas}.ts`, `src/features/organizations/{OrganizationsPage,OrganizationDetailPage,OrganizationFormDrawer,ItResponsiblesPanel}.tsx`, locale files `{uz,ru}/{dashboard,organizations}.json`
- Test: `DashboardPage.test.tsx`, `organizations/schemas.test.ts`, `OrganizationFormDrawer.test.tsx`

**Interfaces:**
- Consumes: Task 14 components/hooks, `GET /dashboard`, organization endpoints (Task 9)
- Produces: routes `/` (dashboard), `/organizations` (tree view + table view toggle), `/organizations/:id` (tabs: info, departments, employees, IT responsibles); `organizationSchema` (zod: `name` required ≤255, `code` required ≤50, `tin` optional `/^\d{9}$/`, `parent_id` required unless `me.is_super_admin`); query keys `['organizations', params]`, `['organizations','tree']`, `['organizations', id]`; mutations invalidate those
- Dashboard renders only widgets present in the response, with the org context from `useUiStore`

- [ ] **Step 1: Failing tests:** `it('renders only widgets returned by the API')`, `it('shows profile card for employee role')`, `it('rejects 8-digit tin')`, `it('requires parent for non super admin')`, `it('maps 422 code error onto the code field')`.
- [ ] **Step 2: Run** → FAIL. **Step 3: Implement** (delete → `confirmAction` → 409 message shown as toast). **Step 4: Run** tests + typecheck + lint + build → PASS.
- [ ] **Step 5: Commit** `feat(frontend): dashboard and organizations`.

---

### Task 16: Departments and employees pages

**Files:**
- Create: `src/features/departments/{api,queries,types,schemas}.ts`, `{DepartmentsPage,DepartmentFormDrawer}.tsx`; `src/features/employees/{api,queries,types,schemas}.ts`, `{EmployeesPage,EmployeeDetailPage,EmployeeFormDrawer}.tsx`; locale files
- Test: `employees/schemas.test.ts`, `EmployeesPage.test.tsx`, `departments/schemas.test.ts`

**Interfaces:**
- Produces: routes `/departments`, `/employees`, `/employees/:id`; `employeeSchema` (zod: `last_name`, `first_name` required ≤100; `phone` optional `/^\+?[0-9\s\-()]{5,20}$/`; `status` enum; `dismissed_at` required when status `dismissed`; `department_id` options reload when `organization_id` changes and reset if not in the new org)
- Employees list filters: organization (`OrganizationSelect`), department, status; search box; dismiss action via status edit with confirm

- [ ] **Step 1: Failing tests:** `it('requires dismissed_at when status is dismissed')`, `it('rejects malformed phone')`, `it('sends filter[status] and search to the API and reflects them in the URL')`, `it('resets department when organization changes')`.
- [ ] **Step 2–4:** Run → FAIL; implement; run tests + typecheck + lint + build → PASS.
- [ ] **Step 5: Commit** `feat(frontend): departments and employees`.

---

### Task 17: Users and roles pages

**Files:**
- Create: `src/features/users/{api,queries,types,schemas}.ts`, `{UsersPage,UserDetailPage,UserFormDrawer,UserAccessEditor,TemporaryPasswordModal}.tsx`; `src/features/roles/{api,queries,types,schemas}.ts`, `{RolesPage,RoleEditorPage}.tsx`; locale files `{users,roles,permissions}.json` (labels for every permission key)
- Test: `TemporaryPasswordModal.test.tsx`, `RoleEditorPage.test.tsx`, `users/schemas.test.ts`

**Interfaces:**
- Produces: routes `/users`, `/users/:id`, `/roles`, `/roles/:id` (and `/roles/new`); `userSchema` (`username` `/^[a-z0-9._-]{3,64}$/`, `email` optional email, `organization_id` required unless super admin, `employee_id` optional — selecting an employee locks organization to theirs); access editor rows `{organization_id, include_descendants}`; role editor = permission matrix grouped by module with "select all in module"
- `TemporaryPasswordModal` shows the password once with copy button and warning text; closing discards it from state

- [ ] **Step 1: Failing tests:** `it('shows the temporary password with a copy button and clears it on close')`, `it('toggling a module header selects all its permissions')`, `it('rejects uppercase usernames')`.
- [ ] **Step 2–4:** Run → FAIL; implement; run tests + typecheck + lint + build → PASS.
- [ ] **Step 5: Commit** `feat(frontend): users, access editor and roles`.

---

### Task 18: Audit, settings, profile and notifications pages

**Files:**
- Create: `src/features/audit/{api,queries,types}.ts`, `{AuditLogPage,AuditDiff}.tsx`; `src/features/settings/{api,queries}.ts`, `SettingsPage.tsx`; `src/features/profile/ProfilePage.tsx`; `src/features/notifications/NotificationsPage.tsx`; locale files
- Test: `AuditDiff.test.tsx`, `SettingsPage.test.tsx`, `NotificationsPage.test.tsx`

**Interfaces:**
- Produces: routes `/audit` (filters: event, user, entity type, date range via `@mantine/dates` → `created_from/created_to` as `YYYY-MM-DD`), `/settings` (grouped `general`/`security`, typed inputs, submits only changed keys), `/profile` (info, locale switch → `PATCH /auth/me` + `i18n.changeLanguage`, link to change password), `/notifications`
- `AuditDiff` props `{oldValues: Record<string, unknown> | null, newValues: …}` → table of field / old / new

- [ ] **Step 1: Failing tests:** `it('AuditDiff lists changed fields old → new')`, `it('SettingsPage submits only changed values')`, `it('mark all as read calls the endpoint and clears unread badges')`.
- [ ] **Step 2–4:** Run → FAIL; implement; run tests + typecheck + lint + build → PASS.
- [ ] **Step 5: Commit** `feat(frontend): audit log, settings, profile and notifications`.

---

### Task 19: Production images, operations scripts, README, end-to-end verification

**Files:**
- Create: `scripts/{backup.sh,restore.sh,selfsigned-cert.sh,smoke.sh}`, `README.md`
- Modify: `docs/ROADMAP.md` (Phase 0/1 ✅), `docs/ARCHITECTURE.md` (any deviations found during build), `compose.prod.yaml` if verification finds issues

**Interfaces:**
- Produces:
  - `backup.sh [dir]`: `mysqldump --single-transaction --routines` via `docker compose exec -T mysql` using app credentials → `backups/<UTC timestamp>/db.sql.gz`, plus `tar` of the `app-storage` volume; deletes backups older than `BACKUP_RETENTION_DAYS` (default 14)
  - `restore.sh <backup dir>`: confirms, restores DB and storage
  - `smoke.sh [base_url]`: csrf-cookie → login (`SMOKE_USERNAME`/`SMOKE_PASSWORD`) → `GET /auth/me` → logout; exits non-zero on any failure
  - README (Uzbek): requirements, quick start (`./scripts/init-env.sh`, `docker compose up -d --build`, `make migrate seed`, `app:create-super-admin`), environments (`COMPOSE_FILE`), production checklist (APP_DEBUG=false, secure cookies, TLS via certs dir or upstream proxy + `TRUSTED_PROXIES`, restricted DB user incl. optional `REVOKE UPDATE, DELETE ON audit_logs`), logs (`docker compose logs -f <svc>`), updates (`git pull && docker compose up -d --build`), backup/restore + restore test procedure, testing/lint commands, demo accounts (dev only)

- [ ] **Step 1: Prod-mode verification** on project name `aturtg-prodcheck` with a temp env file (`APP_ENV=production`, `APP_DEBUG=false`, `APP_PORT` n/a, ports `8081:80`/`8443:443` override) and `selfsigned-cert.sh`: `docker compose -p aturtg-prodcheck --env-file <tmp> -f compose.yaml -f compose.prod.yaml up -d --build`. Expected: all healthy; `curl -sk https://localhost:8443/api/v1/health` → `{"data":{"status":"ok"}}`; `curl -sk https://localhost:8443/api/v1/nope` has no stack trace; `docker compose -p aturtg-prodcheck port mysql 3306` → empty; backend image has no `vendor/phpunit` (`docker compose … exec backend test ! -d vendor/phpunit`); `smoke.sh https://localhost:8443` with a CLI-created super admin passes. Tear down with `down -v`.
- [ ] **Step 2: Backup/restore drill** on the dev stack: create a marker organization, `backup.sh`, delete marker, `restore.sh`, marker exists again.
- [ ] **Step 3: Write README and update docs.**
- [ ] **Step 4: Full gates on a clean dev stack:** `docker compose down -v && ./scripts/init-env.sh && docker compose up -d --build && make migrate seed && make check` → all PASS; `smoke.sh http://localhost:8095` passes with a demo account.
- [ ] **Step 5: Commit** `chore: production verification, backup/restore scripts and README`.

---

## Final Review

After Task 19: whole-branch review (superpowers:requesting-code-review), fix findings, re-run `make check`, then report per spec §13 (done / remaining / commands / next phase) before starting Phase 2.
