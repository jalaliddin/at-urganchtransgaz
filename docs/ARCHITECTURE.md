# AT.urtg.uz — Axborot texnologiyalari xizmatini boshqarish tizimi

## Arxitektura hujjati

| | |
|---|---|
| Holat | 1-bosqich (Foundation) uchun tasdiqlangan |
| Sana | 03.11.2026 |
| Bog'liq hujjatlar | [ROADMAP.md](ROADMAP.md), [DATABASE.md](DATABASE.md), [API.md](API.md), [OPEN_QUESTIONS.md](OPEN_QUESTIONS.md) |

---

## 1. Umumiy ko'rinish

AT.urtg.uz — "Urganchtransgaz" MCHJ markaziy apparati va quyi tashkilotlaridagi
AT xizmatlari faoliyatini yagona tizimda yurituvchi web-platforma: Helpdesk,
IT-aktivlar (CMDB), ombor, talabnomalar, monitoring, SIM-kartalar, aloqa
to'lovlari, Kanban topshiriqlar, printerlar va hisobotlar.

```
              Brauzer (desktop / planshet / mobil)
                          │  HTTPS
                          ▼
                 ┌──────────────────┐
                 │  nginx (edge)    │  TLS, xavfsizlik headerlari, routing
                 └──┬────────────┬──┘
     /api, /sanctum,│            │ qolgan barcha yo'llar
     /docs          ▼            ▼
          ┌──────────────┐  ┌──────────────┐
          │ backend      │  │ frontend     │  React SPA (prod: statik build)
          │ PHP-FPM 8.3  │  └──────────────┘
          │ Laravel 13   │
          └──┬───────┬───┘
             │       │          ┌──────────────┐  ┌──────────────┐
             │       └─────────►│ redis        │◄─┤ queue-worker │
             ▼                  │ cache, queue │  └──────────────┘
      ┌──────────────┐          └──────────────┘  ┌──────────────┐
      │ mysql 8.4    │◄───────────────────────────┤ scheduler    │
      │ (volume)     │                            └──────────────┘
      └──────────────┘
```

Asosiy tamoyillar:

1. **Yagona ma'lumotlar modeli.** Tashkilot, xodim, foydalanuvchi va ruxsatlar
   barcha modullar uchun umumiy. Modullar bir-biriga foreign key orqali bog'lanadi,
   ma'lumot nusxalanmaydi.
2. **Tashkilot doirasi (organization scope) backendda majburiy.** Frontenddagi menyu
   yashirish faqat qulaylik uchun, xavfsizlik emas.
3. **Bosqichma-bosqich, har doim ishlaydigan holat.** Har bir bosqich migratsiya,
   validatsiya, avtorizatsiya, test va hujjat bilan yakunlanadi.
4. **Hech narsa kodga qotirilmaydi:** tashkilotlar, rollar, sozlamalar —
   ma'lumotlar bazasida; maxfiy qiymatlar — `.env`da.

## 2. Texnologiyalar

| Qatlam | Tanlov | Izoh |
|---|---|---|
| Backend | PHP 8.3, Laravel 13 | Spec "Laravel 12 yoki eng barqaror versiya" — Laravel 13 joriy barqaror versiya |
| Auth | Laravel Sanctum 4 (SPA cookie sessiya) | Bearer tokenlar keyin agentlar/integratsiyalar uchun |
| RBAC | spatie/laravel-permission 8 | Teams rejimisiz; tashkilot doirasi alohida jadvalda |
| So'rov filtrlari | spatie/laravel-query-builder 6 | Faqat ruxsat etilgan filter/sort maydonlari |
| API hujjatlari | dedoc/scramble | Koddan avtomatik OpenAPI 3.1 |
| Sifat | PHPUnit, Laravel Pint, Larastan | |
| DB | MySQL 8.4 LTS | utf8mb4, UTC |
| Cache/Queue | Redis 7 | Sessiyalar DB'da (bekor qilish imkoniyati uchun) |
| Frontend | React 19, TypeScript 6 (strict), Vite 8 | |
| UI | Mantine 9, mantine-datatable, Tabler icons | Light asosiy, dark qo'llab-quvvatlanadi |
| Server state | TanStack Query 5 | |
| Client state | Zustand (UI holati, tashkilot konteksti) | |
| Formalar | React Hook Form + Zod 4 | |
| i18n | i18next + react-i18next, dayjs | `uz` asosiy, `ru` tayyor |
| Frontend test | Vitest, Testing Library, MSW (faqat testlarda) | |
| Infra | Docker Compose, nginx, multi-stage Dockerfile | |

## 3. Repozitoriya tuzilmasi

```
at.urtg.uz/
├── backend/                     Laravel API
│   ├── app/
│   │   ├── Console/Commands/    app:create-super-admin va boshqalar
│   │   ├── Enums/               OrganizationType, EmployeeStatus, AuditEvent ...
│   │   ├── Events/ Listeners/   Modullararo hodisalar (EmployeeDismissed ...)
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/<Modul>/   Yupqa controllerlar
│   │   │   ├── Middleware/      EnsureUserIsActive, EnsurePasswordIsChanged, SetLocale, AssignRequestId
│   │   │   ├── Requests/<Modul>/             Form Request validatsiya
│   │   │   └── Resources/<Modul>/            API Resource (JSON shakli)
│   │   ├── Models/              Eloquent modellar + Concerns (traitlar)
│   │   ├── Notifications/
│   │   ├── Policies/
│   │   ├── Rules/               AccessibleOrganization ...
│   │   ├── Services/<Modul>/    Biznes logika (tranzaksiyalar shu yerda)
│   │   └── Support/             ApiResponse, OrganizationAccess, Settings, Permissions registry
│   ├── config/permissions.php   Ruxsatlar reyestri (modul.amal)
│   ├── config/settings.php      Tizim sozlamalari reyestri
│   ├── database/{migrations,seeders,factories}
│   ├── lang/{uz,ru}/
│   └── tests/{Unit,Feature}/
├── frontend/                    React SPA
│   └── src/
│       ├── app/                 App, providers, router, theme
│       ├── api/                 Axios client, xatolarni normallashtirish, umumiy tiplar
│       ├── components/          DataTable, PageHeader, EmptyState, ErrorState, StatusBadge, ConfirmDialog ...
│       ├── features/<modul>/    Sahifalar, hooklar, API funksiyalari, Zod sxemalar
│       ├── hooks/               usePermissions, useTableParams ...
│       ├── i18n/                i18next sozlamasi, locales/{uz,ru}/*.json
│       ├── layouts/             AppLayout (sidebar, header), AuthLayout
│       ├── lib/                 Sana/pul formatlash, yordamchi funksiyalar
│       ├── stores/              Zustand store'lar
│       └── test/                Vitest setup, MSW handlerlar (faqat test)
├── docker/
│   ├── nginx/                   Edge konfiguratsiya (dev, prod+TLS), certs/
│   ├── php/                     Multi-stage Dockerfile, php.ini, fpm pool, entrypoint
│   ├── frontend/                Dockerfile (dev: Vite, prod: statik)
│   └── mysql/init/              Dev uchun test DB yaratish skripti
├── scripts/                     init-env.sh, backup.sh, restore.sh
├── docs/                        Hujjatlar
├── compose.yaml                 Umumiy servislar
├── compose.dev.yaml             Development qo'shimchalari
├── compose.prod.yaml            Production/staging qo'shimchalari
├── .env.example
└── Makefile
```

Laravel'ning standart tuzilmasi saqlanadi, modullar papka ichida guruhlanadi.
Shu bilan Laravel konvensiyalari buzilmaydi va har bir modulning fayllari bir joyda
turadi.

## 4. Backend arxitekturasi

### 4.1. So'rov hayot sikli

```
HTTP so'rov
 → nginx → PHP-FPM
 → Middleware: AssignRequestId → SetLocale → Sanctum (stateful) → auth:sanctum
               → EnsureUserIsActive → EnsurePasswordIsChanged → throttle
 → Route model binding (tashkilot global scope ishlaydi → begona obyekt = 404)
 → Form Request: authorize() + rules() (AccessibleOrganization qoidasi)
 → Controller (yupqa): Policy tekshiruvi → Service chaqirish → Resource qaytarish
 → Service: DB::transaction, biznes qoidalar, Event dispatch
 → Model traitlari: HasUserStamps (created_by/updated_by), Auditable (audit_logs)
 → JSON javob {data, meta} yoki xato {message, code, errors}
```

### 4.2. Qatlamlar va mas'uliyatlar

| Qatlam | Nima qiladi | Nima qilmaydi |
|---|---|---|
| Controller | Policy chaqiradi, Service'ga uzatadi, Resource qaytaradi | Biznes logika, so'rov yig'ish |
| Form Request | Kiruvchi ma'lumot validatsiyasi, tashkilot doirasi tekshiruvi | DB yozuvi |
| Policy | Obyekt darajasidagi ruxsat: `ruxsat + tashkilotga kirish` | |
| Service | Tranzaksiya, biznes qoidalar, eventlar | HTTP bilan ishlash |
| Resource | JSON shakli, maxfiy maydonlarni yashirish | |
| Event/Listener | Modullararo bog'liqlik (masalan, xodim ishdan bo'shaganda foydalanuvchini o'chirish) | |
| Job | Og'ir/fon ishlar (hisobotlar, ping, bildirishnomalar) | |

### 4.3. Yagona javob formati

Batafsil — [API.md](API.md). Qisqacha:

```json
{ "data": { ... } }
{ "data": [ ... ], "meta": { "current_page": 1, "per_page": 25, "total": 130, "last_page": 6 } }
{ "message": "Ma'lumotlar noto'g'ri.", "code": "validation_error", "errors": { "name": ["..."] } }
```

`App\Support\ApiResponse` va markazlashgan exception handler (`bootstrap/app.php`)
barcha xatolarni shu formatga keltiradi. `APP_DEBUG=false` bo'lsa stack trace,
SQL va fayl yo'llari hech qachon javobga chiqmaydi.

## 5. Tashkiliy model va ma'lumotlarni ajratish

### 5.1. Tashkilotlar daraxti

`organizations` jadvali o'z-o'ziga bog'langan daraxt (`parent_id`) va
**materiallashtirilgan yo'l** (`path`, masalan `/1/5/12/`) bilan saqlanadi.
`path LIKE '/1/5/%'` so'rovi tashkilot va uning barcha quyi tashkilotlarini
bitta indeksli so'rov bilan beradi. Tashkilot boshqa ota-tashkilotga
ko'chirilganda o'zi va avlodlarining `path`/`depth` qiymatlari bitta tranzaksiyada
yangilanadi; tsikl (o'zini o'z avlodiga ko'chirish) taqiqlanadi.

Tashkilotlar soni, turi va ierarxiyasi faqat ma'lumot — kodda hech narsa qotirilmagan.

### 5.2. Foydalanuvchining kirish doirasi (access scope)

```
user_organization_access
  user_id ─┬─ organization_id = 1 (Markaziy apparat), include_descendants = true
           └─ ...
```

- Foydalanuvchi bir yoki bir nechta tashkilotga kirish huquqiga ega;
  `include_descendants = true` bo'lsa, shu tashkilotning barcha hozirgi va
  **kelajakdagi** quyi tashkilotlari ham kiradi.
- Markaziy AT xizmati xodimlariga ildiz tashkilot + avlodlar beriladi.
- Quyi tashkilot foydalanuvchisiga faqat o'z tashkiloti beriladi.
- Super Admin — cheklanmagan (unrestricted).
- Foydalanuvchi xodim kartasiga bog'langanda, uning tashkilotiga kirish huquqi
  avtomatik qo'shiladi (administrator keyin o'zgartirishi mumkin).

`App\Support\OrganizationAccess` xizmati foydalanuvchi uchun ruxsat etilgan
tashkilot ID'lari to'plamini hisoblaydi va so'rov davomida xotirada saqlaydi.

### 5.3. Himoya qatlamlari (defense in depth)

1. **Global scope.** Tashkilotga tegishli har bir model `BelongsToOrganization`
   traitidan foydalanadi. Autentifikatsiyadan o'tgan foydalanuvchi bo'lsa, barcha
   so'rovlarga `organization_id IN (ruxsat etilganlar)` sharti avtomatik qo'shiladi.
   Natijada route model binding orqali begona tashkilot obyektiga murojaat **404**
   qaytaradi (obyekt mavjudligi ham oshkor bo'lmaydi). Scope faqat
   `Auth::hasUser()` bo'lganda ishlaydi — auth jarayonining o'zini sindirmaydi.
2. **Policy.** Har bir amal `ruxsat (modul.amal) && tashkilotga kirish` sharti bilan
   tekshiriladi.
3. **Form Request.** Kiruvchi `organization_id`, `department_id` va boshqa
   bog'lanishlar `AccessibleOrganization` qoidasi va "bo'lim shu tashkilotga
   tegishlimi" tekshiruvi bilan validatsiya qilinadi. Obyektni boshqa tashkilotga
   o'tkazish uchun ikkala tashkilotga ham kirish huquqi kerak.
4. **Testlar.** Har bir modul uchun "begona tashkilot ma'lumotini ko'rish/o'zgartirish
   mumkin emas" feature testlari majburiy.

Fon vazifalari (queue, scheduler) autentifikatsiyasiz ishlaydi va shuning uchun
cheklanmagan. Foydalanuvchi nomidan bajariladigan fon vazifalari (masalan, hisobot
generatsiyasi) foydalanuvchi ID'sini qabul qilib, doirani aniq qo'llashi shart.

## 6. Autentifikatsiya va sessiyalar

- **SPA:** Sanctum stateful (cookie) rejimi. Frontend avval `GET /sanctum/csrf-cookie`,
  so'ng `POST /api/v1/auth/login {username, password, remember}`. Sessiya cookie —
  `HttpOnly`, `Secure` (prod), `SameSite=Lax`. JavaScript tokenni ko'ra olmaydi.
- **Login:** username (unikal). Email ixtiyoriy.
- **Brute-force himoyasi:** `username+IP` bo'yicha 5 urinish/daqiqa, IP bo'yicha
  umumiy limit; har bir muvaffaqiyatsiz urinish audit logga yoziladi (parolsiz).
- **Parol:** Laravel hashing (bcrypt), minimal uzunlik sozlamadan
  (`security.password_min_length`), harf va raqam majburiy.
- **Parolni almashtirish talabi:** `must_change_password = true` (yangi foydalanuvchi,
  admin tomonidan tiklangan parol, CLI orqali yaratilgan Super Admin) yoki
  `security.password_expires_days` muddati o'tgan bo'lsa — faqat `me`, `logout`
  va parol almashtirish endpointlari ishlaydi (`403 password_change_required`).
- **Parolni tiklash:** foydalanuvchida email bo'lsa — email orqali havola
  (javob har doim bir xil, foydalanuvchi mavjudligini oshkor qilmaydi). Email bo'lmasa —
  administrator vaqtinchalik parol generatsiya qiladi (bir marta ko'rsatiladi).
- **Sessiyalar** `database` drayverida: foydalanuvchi faolsizlantirilganda yoki parol
  admin tomonidan tiklanganda uning barcha sessiyalari o'chiriladi. Faolsiz
  foydalanuvchi keyingi so'rovdayoq `401` oladi.
- **MFA/2FA:** `users` jadvalida `two_factor_secret`, `two_factor_recovery_codes`,
  `two_factor_confirmed_at` maydonlari zaxiralangan; login oqimi ikkinchi qadamni
  qo'shishga tayyor (keyingi bosqich).
- **Bearer tokenlar** (Sanctum personal access tokens) — kelajakdagi agentlar va
  integratsiyalar uchun; agent tokenlari alohida `agent_tokens` jadvalida hash
  ko'rinishida saqlanadi (5-bosqich).

## 7. RBAC — rollar va ruxsatlar

- **Ruxsat** = `modul.amal` (masalan `employees.update`). Barcha ruxsatlar kodda
  `config/permissions.php` reyestrida e'lon qilinadi (chunki ular kod
  funksiyalariga mos keladi) va idempotent seeder orqali DB'ga yoziladi.
  Har bir bosqich o'z ruxsatlarini qo'shadi.
- **Rol** — DB'dagi ma'lumot. Boshlang'ich 8 ta rol seed qilinadi; Super Admin
  rollarni yaratadi, tahrirlaydi, ruxsatlarini o'zgartiradi. Tizim rollarini
  (`is_system`) o'chirib bo'lmaydi.
- **Super Admin** `Gate::before` orqali barcha tekshiruvlardan o'tadi va
  tashkilot doirasi bilan cheklanmaydi.
- **Imtiyozni oshirishdan himoya:**
  - Super Admin rolini faqat Super Admin bera oladi.
  - Boshqa foydalanuvchi faqat ruxsatlari o'z ruxsatlarining qism to'plami bo'lgan
    rollarni bera oladi.
  - Tashkilotga kirish huquqini faqat o'zi kira oladigan tashkilotlar doirasida
    bera oladi (`include_descendants` uchun barcha avlodlarga kirish kerak).
  - Foydalanuvchi o'z rollari/kirish doirasini o'zgartira olmaydi, o'zini
    faolsizlantira olmaydi; tizimda kamida bitta faol Super Admin qoladi.

Boshlang'ich rollar va 1-bosqich ruxsatlari matritsasi — [DATABASE.md](DATABASE.md#rollar-va-ruxsatlar-matritsasi).

## 8. Audit

- `Auditable` trait model hodisalarini (created, updated, deleted, restored)
  `audit_logs` jadvaliga yozadi: kim, qachon, qaysi obyekt, eski va yangi qiymatlar,
  IP, user agent, so'rov ID.
- Maxfiy maydonlar (`password`, `remember_token`, 2FA maydonlari, tokenlar)
  hech qachon yozilmaydi.
- Auth hodisalari (login, logout, login_failed, password_changed, password_reset),
  rol/kirish o'zgarishlari va sozlamalar o'zgarishi alohida hodisa sifatida yoziladi.
- `AuditLog` modeli faqat qo'shishga ruxsat beradi (update/delete — exception);
  API'da o'zgartirish endpointi yo'q. Qo'shimcha himoya sifatida productionda
  DB foydalanuvchisiga `audit_logs` uchun faqat `INSERT, SELECT` huquqi berish
  mumkin (DEPLOYMENT bo'limiga qarang).
- Audit yozuvi har doim auditlanayotgan o'zgarish bilan bitta tranzaksiyada yoziladi.
- Audit loglarni ko'rish `audit.view` ruxsati va tashkilot doirasi bilan cheklanadi.

## 9. Fayllar (2-bosqichdan boshlab)

- Fayllar `local` (private) diskida, `storage/app/private` ichida; public URL yo'q.
- Yuklab olish faqat API orqali: avval tegishli obyektga Policy tekshiruvi,
  so'ng stream javob.
- Yuklashda: ruxsat etilgan kengaytmalar ro'yxati, MIME tekshiruvi (`finfo`),
  hajm limiti, bajariladigan/xavfli turlar (php, exe, js, html, svg ...) bloklanadi,
  fayl nomi tasodifiy UUID bilan saqlanadi.
- `documents` + `document_links` (polimorf) orqali har qanday obyektga bog'lanadi,
  versiyalar saqlanadi.

## 10. Bildirishnomalar

- Laravel Notifications, `database` kanali (tizim ichidagi qo'ng'iroqcha).
- Har bir bildirishnoma `via()` metodida foydalanuvchi sozlamalariga qarab
  kanallarni tanlaydi; email, Telegram, SMS kanallari keyin shu interfeys orqali
  qo'shiladi (Telegram uchun custom channel klassi).
- Bildirishnomalar queue orqali yuboriladi.
- 1-bosqichda: kirish huquqi/rollar o'zgarganligi va parol administrator
  tomonidan tiklanganligi haqidagi bildirishnomalar.

## 11. Frontend arxitekturasi

- **Routing:** React Router, `RequireAuth` → `RequirePasswordChanged` →
  `AppLayout` → sahifa; `RequirePermission` sahifa darajasida (backend baribir
  tekshiradi).
- **Server state:** TanStack Query; har bir modulda `api.ts` (so'rov funksiyalari),
  `queries.ts` (query key'lar va hooklar). Mutatsiyadan keyin tegishli query'lar
  invalidatsiya qilinadi.
- **API client:** yagona Axios instance: `baseURL=/api/v1`, `withCredentials`,
  `withXSRFToken`, `Accept-Language`. 401 → login sahifasiga, 419 → CSRF cookie
  yangilanib so'rov bir marta qayta yuboriladi, 403 `password_change_required` →
  parol almashtirish sahifasi. Xatolar `ApiError` turiga normallashtiriladi;
  422 xatolari forma maydonlariga avtomatik bog'lanadi.
- **Jadvallar:** umumiy `DataTable` (mantine-datatable): server-side pagination,
  sort, filtr, ustunlar ko'rinishi; holat URL query parametrlarida saqlanadi
  (havolani ulashish mumkin). Loading skeleton, empty state, error state + qayta
  urinish har bir jadvalda.
- **Layout:** chap sidebar (yig'iladigan, mobilda drawer), yuqori panel: breadcrumb,
  global qidiruv (Spotlight → `/api/v1/search`), bildirishnomalar, tashkilot
  konteksti tanlagichi, profil menyusi.
- **Tashkilot konteksti:** foydalanuvchi o'z doirasidagi tashkilotni tanlasa,
  dashboard va ro'yxatlar shu tashkilot (va quyi tashkilotlari) bo'yicha
  filtrlanadi. Backend tanlangan tashkilotga kirish huquqini tekshiradi.
- **Dizayn tizimi:** Mantine theme (ranglar, radius, shriftlar), statuslar uchun
  yagona `StatusBadge` rang xaritasi, tasdiqlash uchun `ConfirmDialog`,
  toast — `@mantine/notifications`.

## 12. Tillar, vaqt va valyuta

- Asosiy til — o'zbek (lotin). Barcha matnlar `frontend/src/i18n/locales/uz/*.json`
  va `backend/lang/uz/*.php` da. Rus tili uchun `ru` papkalari tayyor.
- Til tanlovi: foydalanuvchi profili (`users.locale`) → `Accept-Language`.
- DB va API'da vaqt **UTC**, ISO 8601 (`2026-11-03T06:30:00Z`). Interfeysda
  `Asia/Tashkent`, format `DD.MM.YYYY` va `HH:mm` (`lib/format.ts`).
- Pul — `DECIMAL(18,2)`, valyuta UZS; interfeysda `1 250 000,00 so'm` ko'rinishida.

## 13. Infratuzilma

| Servis | Image | Vazifasi |
|---|---|---|
| `nginx` | nginx:1.27-alpine | Edge: TLS, `/api`,`/sanctum`,`/docs` → backend; qolgani → frontend |
| `frontend` | dev: node:22 (Vite HMR); prod: statik build + nginx-unprivileged | SPA |
| `backend` | php:8.3-fpm-alpine (multi-stage) | Laravel API |
| `queue-worker` | backend image | `php artisan queue:work` |
| `scheduler` | backend image | `php artisan schedule:work` |
| `mysql` | mysql:8.4 | Persistent volume, port tashqariga ochilmaydi |
| `redis` | redis:7-alpine | Cache va queue, parol bilan |
| `mailpit` | (faqat dev) | Parol tiklash xatlarini sinash |

- Muhitlar: `.env` dagi `COMPOSE_FILE` qiymati orqali — dev:
  `compose.yaml:compose.dev.yaml`, staging/prod: `compose.yaml:compose.prod.yaml`.
  Shu sababli har qanday muhitda `docker compose up -d --build` yetarli.
- Barcha servislarda healthcheck, `depends_on: condition: service_healthy`,
  `restart: unless-stopped`, log rotation (`json-file`, `max-size`, `max-file`).
- Konteynerlarda Laravel loglari `stderr`ga yoziladi va Docker tomonidan aylantiriladi.
- Production: `APP_DEBUG=false`, kod image ichida, dev dependency'lar yo'q,
  OPcache yoqilgan, MySQL root ilova tomonidan ishlatilmaydi.

O'rnatish, yangilash, backup/restore — [README.md](../README.md).

## 14. Xavfsizlik

| Tahdid | Chora |
|---|---|
| IDOR / tashkilotlararo sizish | Global scope + Policy + Form Request + testlar (5.3) |
| SQL injection | Faqat Eloquent/Query Builder binding; filter/sort allowlist |
| XSS | React avtomatik escaping; `dangerouslySetInnerHTML` ishlatilmaydi; CSP header |
| CSRF | Sanctum SPA CSRF (XSRF-TOKEN cookie + header) |
| Mass assignment | Har bir modelda `$fillable`; faqat `validated()` ma'lumot ishlatiladi |
| Brute force | Login throttle, audit |
| Sessiya o'g'irlanishi | HttpOnly/Secure/SameSite cookie, login'da sessiya regeneratsiyasi |
| Imtiyoz oshirish | 7-bo'limdagi qoidalar |
| Maxfiy ma'lumot sizishi | `APP_DEBUG=false`, umumiy xato xabarlari, parol/tokenlar logga yozilmaydi, `$hidden` |
| Fayllar | Private storage, MIME/hajm/tur tekshiruvi, ruxsat orqali yuklab olish |
| Monitoring buyruqlari (5-bosqich) | Shell ishlatilmaydi, IP/CIDR allowlist, `Process` argumentlar massivi bilan |

## 15. Kengaytirish nuqtalari

- **Windows agent / AD / antivirus konsoli** (5-bosqich): `device_agents`,
  `agent_tokens` (hash), `agent_checkins`, `software_inventory`. Agent faqat o'z
  qurilmasi ma'lumotini yubora oladi.
- **ICMP monitoring** (5-bosqich): alohida `monitor` worker servisi, minimal
  `NET_RAW` capability, queue orqali parallel, rate limit bilan.
- **Telegram/SMS/email** bildirishnomalar: Notification channel klasslari.
- **Hisobotlar eksporti** (2-bosqichdan): queue orqali generatsiya, private
  storage'ga saqlash, tayyor bo'lganda bildirishnoma.
