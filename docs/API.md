# AT.urtg.uz — API standartlari

| | |
|---|---|
| Asosiy yo'l | `/api/v1` |
| Format | JSON (`Accept: application/json`) |
| Interaktiv hujjat | `/docs/api` (Scramble, OpenAPI 3.1). Productionda faqat Super Admin uchun |
| OpenAPI fayli | `/docs/api.json` |

## 1. Autentifikatsiya (SPA)

```
1. GET  /sanctum/csrf-cookie              → XSRF-TOKEN cookie o'rnatiladi
2. POST /api/v1/auth/login                → sessiya cookie
   Header: X-XSRF-TOKEN: <cookie qiymati>
   Body:   {"username": "a.karimov", "password": "...", "remember": false}
3. Keyingi barcha so'rovlar cookie + X-XSRF-TOKEN bilan
4. POST /api/v1/auth/logout
```

Axios `withCredentials: true` va `withXSRFToken: true` bilan buni avtomatik bajaradi.
Integratsiyalar (keyingi bosqichlar) uchun `Authorization: Bearer <token>`.

## 2. Javob formati

**Bitta obyekt**

```json
{ "data": { "id": 12, "name": "Xorazm GTS", "created_at": "2026-11-03T06:30:00Z" } }
```

**Ro'yxat (pagination)**

```json
{
  "data": [ { "id": 1 }, { "id": 2 } ],
  "meta": { "current_page": 1, "per_page": 25, "total": 130, "last_page": 6, "from": 1, "to": 25 }
}
```

**Amal natijasi (resurs qaytarmaydigan)**

```json
{ "message": "Parol muvaffaqiyatli o'zgartirildi." }
```

**Xato**

```json
{
  "message": "Kiritilgan ma'lumotlar noto'g'ri.",
  "code": "validation_error",
  "errors": { "username": ["Bu login band."] }
}
```

| HTTP | `code` | Holat |
|---|---|---|
| 400 | `bad_request` | Noto'g'ri so'rov |
| 401 | `unauthenticated` | Sessiya yo'q yoki tugagan, foydalanuvchi faolsiz |
| 403 | `forbidden` | Ruxsat yo'q |
| 403 | `password_change_required` | Avval parolni almashtirish kerak |
| 404 | `not_found` | Obyekt yo'q **yoki doiradan tashqarida** |
| 405 | `method_not_allowed` | Yo'l uchun bu HTTP metod qo'llab-quvvatlanmaydi |
| 409 | `conflict` | Biznes qoidasi buzildi (masalan, bolalari bor tashkilotni o'chirish) |
| 419 | `csrf_token_mismatch` | CSRF token eskirgan |
| 422 | `validation_error` | Validatsiya |
| 429 | `too_many_requests` | Rate limit (`Retry-After` header) |
| 500 | `server_error` | Ichki xato — tafsilotlarsiz, `request_id` bilan (`APP_DEBUG=true` bo'lsa `debug` bloki qo'shiladi) |
| boshqa | `http_error` | Yuqoridagilarga kirmaydigan HTTP xatolar |

Har bir javobda `X-Request-Id` header bo'ladi — loglar va audit bilan bog'lash uchun.

## 3. Ro'yxatlar: pagination, filtr, sort, qidiruv

```
GET /api/v1/employees?page=2&per_page=50&sort=-created_at,last_name
                     &filter[status]=active&filter[organization]=5&search=karim
```

| Parametr | Qoida |
|---|---|
| `page` | 1 dan boshlanadi |
| `per_page` | 1–100, standart 25 |
| `sort` | Vergul bilan; `-` kamayish tartibi. Faqat ruxsat etilgan maydonlar, aks holda 400 |
| `filter[<maydon>]` | Faqat ruxsat etilgan filtrlar; bir nechta qiymat vergul bilan |
| `filter[organization]` | Tashkilot **va uning quyi tashkilotlari** (foydalanuvchi doirasi bilan kesishma) |
| `search` | Modulga xos matnli qidiruv (LIKE, maxsus belgilar ekranlangan) |

Sana filtrlari: `filter[created_from]=2026-11-01`, `filter[created_to]=2026-11-30`
(Asia/Tashkent kuni sifatida talqin qilinadi).

## 4. Konvensiyalar

- REST nomlash: `GET /employees`, `POST /employees`, `GET /employees/{id}`,
  `PUT /employees/{id}`, `DELETE /employees/{id}`.
- Holat o'zgartiruvchi amallar — alohida fe'l endpointlari:
  `POST /users/{id}/deactivate`, `POST /users/{id}/reset-password`.
- Vaqt — ISO 8601 UTC; sana — `YYYY-MM-DD`; pul — satr (`"1250000.00"`), aniqlik
  yo'qolmasligi uchun.
- Bog'liq obyektlar ixcham ko'rinishda (`{"id": 5, "name": "..."}`) qaytariladi.
- Har bir resursda `can` obyekti — joriy foydalanuvchi bu obyekt ustida qaysi
  amallarni bajara olishi (`{"update": true, "delete": false}`). Frontend tugmalarni
  shunga qarab ko'rsatadi, backend baribir tekshiradi.

## 5. Rate limiting

| Guruh | Limit |
|---|---|
| `POST /auth/login` | 5/daqiqa (`username+IP`), 30/daqiqa (IP) |
| `POST /auth/forgot-password`, `/auth/reset-password` | 5/daqiqa (IP) |
| Autentifikatsiyalangan API | 120/daqiqa (foydalanuvchi) |
| Ommaviy endpointlar (`/health`, `/settings/public`) | 60/daqiqa (IP) |

## 6. Idempotentlik

Takror yuborilishi zarar keltiradigan operatsiyalar (ombor chiqimi, resurs ajratish,
to'lov qayd etish — 4–6-bosqichlar) `Idempotency-Key` headerini qabul qiladi:
bir xil kalit bilan 24 soat ichidagi takroriy so'rov birinchi natijani qaytaradi.
1-bosqich operatsiyalari tabiatan idempotent (PUT) yoki unikal cheklovlar bilan
himoyalangan.

## 7. 1-bosqich endpointlari

### Ommaviy

| Metod | Yo'l | Izoh |
|---|---|---|
| GET | `/health` | `{"status":"ok"}`; DB/cache tekshiruvi, tafsilotsiz |
| GET | `/settings/public` | Login sahifasi uchun: tizim nomi, tashkilot nomi, yordam kontaktlari |

### Auth

| Metod | Yo'l | Izoh |
|---|---|---|
| POST | `/auth/login` | `{username, password, remember}` |
| POST | `/auth/logout` | |
| GET | `/auth/me` | Foydalanuvchi, rollar, ruxsatlar, kirish doirasi, `must_change_password` |
| PATCH | `/auth/me` | `{locale}` — shaxsiy sozlamalar |
| PUT | `/auth/password` | `{current_password, password, password_confirmation}`; boshqa sessiyalar yopiladi |
| POST | `/auth/forgot-password` | `{login}` (username yoki email) — javob doimo bir xil |
| POST | `/auth/reset-password` | `{token, email, password, password_confirmation}` |

### Dashboard va qidiruv

| Metod | Yo'l | Ruxsat |
|---|---|---|
| GET | `/dashboard?organization_id=` | `dashboard.view`; vidjetlar ruxsatlarga qarab |
| GET | `/search?q=` | Natijalar har bir modul `*.view` ruxsati va doirasi bo'yicha |

### Tashkilotlar

| Metod | Yo'l | Ruxsat |
|---|---|---|
| GET | `/organizations` | `organizations.view` — filtr: `type`, `is_active`, `parent`; sort: `name`, `code`, `sort_order` |
| GET | `/organizations/tree` | `organizations.view` — doiradagi daraxt |
| POST | `/organizations` | `organizations.create` |
| GET | `/organizations/{id}` | `organizations.view` |
| PUT | `/organizations/{id}` | `organizations.update` (ota-tashkilotni o'zgartirish = ko'chirish) |
| DELETE | `/organizations/{id}` | `organizations.delete` — bolalari/xodimlari bo'lsa 409 |
| GET | `/organizations/{id}/it-responsibles` | `organizations.view` |
| POST | `/organizations/{id}/it-responsibles` | `organizations.manage_it_staff` — `{employee_id, is_primary, assigned_at}` |
| DELETE | `/organizations/{id}/it-responsibles/{employee_id}` | `organizations.manage_it_staff` |

### Bo'limlar

| Metod | Yo'l | Ruxsat |
|---|---|---|
| GET | `/departments` | `departments.view` — filtr: `organization`, `is_active`, `parent` |
| POST/GET/PUT/DELETE | `/departments`, `/departments/{id}` | `departments.*` — xodimlari bo'lsa o'chirish 409 |

### Xodimlar

| Metod | Yo'l | Ruxsat |
|---|---|---|
| GET | `/employees` | `employees.view` — filtr: `organization`, `department`, `status`; qidiruv: F.I.Sh., tabel, telefon |
| POST/GET/PUT/DELETE | `/employees`, `/employees/{id}` | `employees.*`. `status=dismissed` → bog'langan foydalanuvchi faolsizlantiriladi |

### Foydalanuvchilar va rollar

| Metod | Yo'l | Ruxsat |
|---|---|---|
| GET | `/users` | `users.view` — filtr: `organization`, `role`, `is_active` |
| POST | `/users` | `users.create` — `{username, name, email?, organization_id?, employee_id?, roles[], organization_access[]}`; vaqtinchalik parol javobda bir marta qaytadi |
| GET | `/users/{id}` | `users.view` |
| PUT | `/users/{id}` | `users.update` |
| POST | `/users/{id}/deactivate`, `/users/{id}/activate` | `users.deactivate` — sessiyalar yopiladi |
| POST | `/users/{id}/reset-password` | `users.reset_password` — vaqtinchalik parol, `must_change_password=true` |
| PUT | `/users/{id}/roles` | `users.manage_access` — `{roles: ["org-it-responsible"]}` |
| PUT | `/users/{id}/organization-access` | `users.manage_access` — `{access: [{organization_id, include_descendants}]}` |
| GET | `/roles` | `roles.view` |
| POST/PUT/DELETE | `/roles`, `/roles/{id}` | `roles.manage` (tizim rollarini o'chirib bo'lmaydi) |
| GET | `/permissions` | `roles.view` — modullar bo'yicha guruhlangan reyestr |

### Audit, sozlamalar, bildirishnomalar

| Metod | Yo'l | Ruxsat |
|---|---|---|
| GET | `/audit-logs` | `audit.view` — filtr: `event`, `user`, `auditable_type`, `auditable_id`, `organization`, `created_from/to` |
| GET | `/audit-logs/{id}` | `audit.view` |
| GET | `/settings` | `settings.view` — reyestr + joriy qiymatlar |
| PUT | `/settings` | `settings.update` — `{values: {"security.password_min_length": 12}}` |
| GET | `/notifications` | O'ziniki — filtr: `unread` |
| GET | `/notifications/unread-count` | O'ziniki |
| POST | `/notifications/{id}/read`, `/notifications/read-all` | O'ziniki |

## 8. 2-bosqich endpointlari

### Helpdesk sozlamalari

| Metod | Yo'l | Ruxsat |
|---|---|---|
| GET | `/ticket-categories` | Helpdeskdan foydalanuvchi (`view`/`create`/`manage`) — `filter[active]=1` |
| POST/PUT/DELETE | `/ticket-categories[/{id}]` | `helpdesk.manage`; arizasi bor kategoriyani o'chirish — 409 |
| GET | `/ticket-sla-policies` | Helpdeskdan foydalanuvchi |
| POST/PUT/DELETE | `/ticket-sla-policies[/{id}]` | `helpdesk.manage` — `{priority, category_id?, response_minutes, resolution_minutes}` (ish daqiqalari) |
| GET/PUT | `/helpdesk/calendar` | GET — foydalanuvchi, PUT — `helpdesk.manage`: `work_days` (1–7), `work_start/end`, `lunch_start/end` (`H:i`), `holidays` (`Y-m-d`), `auto_close_days`, `reopen_days`, `due_soon_minutes` |

### Arizalar

| Metod | Yo'l | Ruxsat / izoh |
|---|---|---|
| GET | `/helpdesk/tickets` | O'z arizalari + `helpdesk.view` bo'lsa doiradagi barchasi. Filtr: `status` (vergul bilan), `priority`, `category`, `organization` (daraxt), `assignee` (`me`/`none`/id), `requester=me`, `overdue=1`, `created_from/to` (`Y-m-d`); `search` (raqam, sarlavha, aloqa); sort: `created_at`, `priority`, `resolution_due_at`, `number`, `status` |
| POST | `/helpdesk/tickets` | `helpdesk.create`; JSON yoki multipart (`files[]` ≤ 5). Boshqa xodim nomidan (`requester_employee_id`) — faqat `helpdesk.process` |
| GET | `/helpdesk/tickets/{id}` | ko'rinadigan bo'lsa; javobda `allowed_actions`, `allowed_transitions` |
| PATCH | `/helpdesk/tickets/{id}` | murojaatchi — faqat `new` holatida; bajaruvchi — ochiq ariza (ustuvorlik/kategoriya o'zgarsa SLA qayta hisoblanadi) |
| POST | `/helpdesk/tickets/{id}/transition` | `{status, note}` — workflow xaritasi (spec §4); `resolved`/`rejected` uchun `note` majburiy; noto'g'ri o'tish — 422 `invalid_transition` |
| POST | `/helpdesk/tickets/{id}/assign` | `helpdesk.assign` — `{assignee_id}` (bajaruvchi, tashkilotga kirishi bor) |
| POST | `/helpdesk/tickets/{id}/take` | `helpdesk.process` — o'ziga olish |
| POST | `/helpdesk/tickets/{id}/rate` | murojaatchi, `resolved/closed` — `{rating: 1..5, comment?}` |
| POST | `/helpdesk/tickets/{id}/time` | bajaruvchi — `{minutes: 1..1440, note?}` |
| GET/POST | `/helpdesk/tickets/{id}/comments` | ishtirokchilar; `is_internal` — faqat bajaruvchi |
| GET | `/helpdesk/tickets/{id}/history` | ichki yozuvlar faqat bajaruvchiga |
| GET/POST | `/helpdesk/tickets/{id}/attachments` | multipart `files[]` ≤ 5, `comment_id?` |
| DELETE | `/helpdesk/tickets/{id}/attachments/{attachmentId}` | yuklagan — 15 daqiqa ichida; bajaruvchi — har doim |
| GET/POST/DELETE | `/helpdesk/tickets/{id}/links[/{linkId}]` | POST/DELETE — bajaruvchi: `{linked_ticket_id, type: relates|duplicates}` |
| GET | `/helpdesk/tickets/{id}/documents` | bog'langan hujjatlar (`documents.view` bo'lsa) |
| GET | `/helpdesk/assignees?organization_id=` | `helpdesk.assign` yoki `process` |
| GET | `/helpdesk/stats` | `helpdesk.view` — ro'yxat filtrlari bilan: holat/ustuvorlik/kategoriya/tashkilot kesimi, SLA bajarilishi, o'rtacha hal qilish vaqti (ish daqiqalari) |

### Fayllar, hujjatlar, eksport

| Metod | Yo'l | Ruxsat / izoh |
|---|---|---|
| GET | `/files/{uuid}` | faqat egasi ruxsat bersa (aks holda 404); `?inline=1` — faqat rasmlar |
| GET/POST/PUT/DELETE | `/documents[/{id}]` | `documents.view` / `documents.manage`; POST multipart `file` (1-versiya). Filtr: `type`, `organization`, `date_from/to`; `search` |
| POST | `/documents/{id}/versions` | `documents.manage` — multipart `file`, `note?` |
| POST/DELETE | `/documents/{id}/links[/{linkId}]` | `{type: "ticket", id}` — faqat ko'rinadigan obyektga |
| GET | `/exports` | o'z eksportlari |
| POST | `/exports` | `{type: "helpdesk_tickets", format: "xlsx"|"pdf", filters}` → 202; navbatda yaratiladi, tayyor bo'lganda bildirishnoma. 10/daq |
| GET | `/exports/{uuid}` | o'ziniki; `file.uuid` orqali `/files/{uuid}` dan yuklab olinadi |

`PATCH /auth/me` endi `notification_preferences.mail_muted` (email orqali yubormaslik turlari) ni ham qabul qiladi.

## 9. 3-bosqich endpointlari (IT aktivlar)

| Metod | Yo'l | Ruxsat / izoh |
|---|---|---|
| GET | `/asset-categories`, `/manufacturers`, `/asset-models`, `/locations` | aktivlardan foydalanuvchi (`assets.view/create/inventory`) |
| POST/PUT/DELETE | `/asset-categories`, `/manufacturers`, `/asset-models` | `assets.manage_catalog`; ishlatilayotgan yozuv — 409 |
| POST/PUT/DELETE | `/locations` | `assets.update`, o'z tashkiloti doirasida |
| GET | `/assets` | `assets.view` — filtr: `status` (vergul), `category`, `organization` (daraxt), `department`, `location`, `holder` (`none`/id), `responsible`, `model`, `manufacturer`, `warranty` (`expiring`/`expired`); `search` (inventar, seriya, hostname, IP, MAC, nom) |
| POST / GET / PATCH / DELETE | `/assets[/{id}]` | `assets.create` / `view` / `update` / `delete` (tarixi bor aktivni o'chirib bo'lmaydi — 409). PATCH'da `organization_id` va `status` taqiqlangan |
| GET | `/assets/lookup/{inventar}` | QR yorlig'i maqsadi; doiradan tashqarida — 404 |
| GET | `/assets/stats` | `assets.view` — holat, toifa, tashkilot kesimi, jami qiymat, kafolat |
| GET | `/assets/mine` | har qanday foydalanuvchi — o'ziga biriktirilgan aktivlar |
| POST | `/assets/{id}/status` | `assets.update` — faqat `in_stock`, `reserve`, `lost` |
| POST | `/assets/{id}/assign`, `/return` | `assets.assign` — `{employee_id, note}` |
| GET | `/assets/{id}/events`, `/assignments`, `/attachments`, `/relations`, `/maintenances`, `/documents` | `assets.view` |
| POST/DELETE | `/assets/{id}/attachments`, `/relations` | `assets.update` |
| POST | `/assets/{id}/maintenances`, `/maintenances/{m}/close` | `assets.update` — `in_repair` ↔ oldingi holat |
| POST | `/assets/{id}/disposals` | `assets.update` — hisobdan chiqarish so'rovi |
| GET / POST | `/asset-disposals`, `/{d}/approve`, `/{d}/reject` | ro'yxat — `assets.view`; qaror — `assets.dispose` |
| GET | `/asset-transfers/destinations` | `assets.transfer` — barcha faol tashkilotlar (faqat id va nom) |
| GET / POST | `/asset-transfers`, `/{t}`, `/{t}/items`, `/{t}/send`, `/{t}/receive`, `/{t}/cancel` | `assets.transfer`: jo'natuvchi — tahrirlash/jo'natish/bekor qilish, qabul qiluvchi — qabul; dalolatnoma `act.uuid` → `/files/{uuid}` |
| GET / POST | `/inventory-sessions`, `/{s}`, `/{s}/items?filter[state]=found|missing|unexpected`, `/{s}/scan`, `/{s}/close` | boshlash/skan/yakunlash — `assets.inventory`; `code` — inventar raqami yoki QR URL |
| GET | `/employees/{id}/assets` | `assets.view` |
| POST | `/exports` `{type: "assets"}` | `assets.export` |

## 10. 4-bosqich endpointlari (Ombor va talabnomalar)

### Ombor

| Metod | Yo'l | Ruxsat / izoh |
|---|---|---|
| GET | `/units`, `/material-categories`, `/materials`, `/warehouses` | `warehouse.view` yoki talabnoma ishtirokchisi (`requisitions.create/review/fulfill`); omborlar — tashkilot doirasi |
| POST/PUT/DELETE | `/units`, `/material-categories`, `/materials`, `/warehouses` | `warehouse.manage`; ishlatilayotgan yozuv — 409 |
| GET | `/stock-balances` | `warehouse.view` — ombor + material kesimida qoldiq, qiymat, minimal, eng yaqin muddat va partiyalar; filtr: `warehouse`, `material`, `category`, `organization`, `search`, `low`, `expiring` |
| GET | `/stock-availability?warehouse_id&material_id` | `warehouse.view` — FIFO tartibidagi partiyalar va jami (qator muharriri uchun) |
| GET / POST | `/stock-documents` | ro'yxat — `warehouse.view` (filtr: `type` vergul, `warehouse`, `material`, `organization`, `date_from`, `date_to`, `search`); joylash: `receipt`/`return` — `warehouse.receive`, `issue` — `warehouse.issue` (`reason` majburiy), `transfer` — `warehouse.transfer` (ikkala ombor doirada), `adjustment` — `warehouse.manage` (`reason` majburiy) |
| GET | `/stock-documents/{id}` | qatorlar, harakatlar, chiqimga qaytarishlar; doiradan tashqarida — 404 |
| POST | `/stock-documents/{id}/file` | joylagan tomon — skan hujjat (fayl `/files/{uuid}`) |
| GET | `/stock-movements` | `warehouse.view` — jurnal; filtr: `warehouse`, `material`, `document`, `type`, `date_from`, `date_to` |
| GET / POST | `/stock-counts`, `/stock-counts/{id}` | boshlash — `warehouse.inventory` (bitta ombor uchun bitta ochiq sanoq, aks holda 409) |
| PATCH / POST | `/stock-counts/{id}/lines` | sanalgan miqdorlar (`{lines: [{id, counted_quantity}]}`) / hisobda yo'q partiya qo'shish |
| POST | `/stock-counts/{id}/close`, `/cancel` | yopish — hamma qator sanalgan bo'lishi kerak; farqlar bitta tuzatish hujjatiga |
| POST | `/exports` `{type: "stock_balances" \| "stock_movements"}` | `warehouse.export` |

Xatolar: `insufficient_stock` (422, `shortages: [{material_id, material, batch, available, requested}]`),
`warehouse_counting` (409 — omborda ochiq inventarizatsiya).

### Talabnomalar

| Metod | Yo'l | Ruxsat / izoh |
|---|---|---|
| GET | `/requisitions` | `requisitions.view` yoki `create`; filtr: `tab` (`mine`, `action` — mening navbatim), `status` (vergul), `organization`, `search`. Qoralamani faqat yaratuvchi ko'radi |
| POST / PUT / DELETE | `/requisitions[/{id}]` | yaratish — `requisitions.create` (doiradagi tashkilot uchun); tahrir/o'chirish — faqat yaratuvchi, faqat `draft` |
| POST | `/requisitions/{id}/submit` | yaratuvchi: `draft` → `org_approval` (rahbar tasdig'i yoqilgan va tashkilotda tasdiqlovchi bor) yoki `submitted` |
| POST | `/requisitions/{id}/org-decision` `{decision, comment}` | `requisitions.approve_org` (o'z talabnomasi emas): approved → `submitted`, rejected → `rejected`, returned → `draft`; rad/qaytarishda izoh majburiy |
| POST | `/requisitions/{id}/take`, `/request-info`, `/provide-info` | ko'rib chiqish — `requisitions.review`; ma'lumot berish — so'rovchi tomoni |
| POST | `/requisitions/{id}/decision` `{decision, comment, items: [{id, approved_quantity}]}` | `requisitions.approve` (o'z talabnomasi emas), `submitted`/`central_review` dan; miqdorni faqat kamaytirish mumkin |
| POST | `/requisitions/{id}/ready`, `/allocations`, `/complete` | `requisitions.fulfill`; ajratish: `{item_id, type: stock\|asset\|note, quantity, warehouse_id, target_warehouse_id?, material_id?, batch?, asset_id?, note?}` — tasdiqlangan qoldiqdan oshmaydi |
| POST | `/requisitions/{id}/receive` | so'rovchi tomoni: `fulfilled` → `received`; boshqa tashkilotdan kelayotgan aktivlar shu paytda qabul qilinadi |
| POST | `/requisitions/{id}/close` | `requisitions.approve` yoki `fulfill`: `received` → `closed` |
| POST | `/requisitions/{id}/comments`, `/attachments` | ishtirokchilar, yakunlanmagan talabnomada |
| POST | `/exports` `{type: "requisitions"}` | `requisitions.export` |

Javobdagi `can` obyekti har bir amal uchun joriy foydalanuvchi huquqini beradi (tugmalar shu bo'yicha ko'rsatiladi).

## 11. 5-bosqich endpointlari (Dasturlar, litsenziyalar, antivirus, agentlar)

| Metod | Yo'l | Ruxsat / izoh |
|---|---|---|
| GET | `/software`, `/software/{id}` | `software.view/install/manage` — katalog (`search`, `filter[category]`, `filter[antivirus]`); kartada qurilmalaringizdagi versiyalar va litsenziyalar |
| POST/PUT/DELETE | `/software` | `software.manage`; nom normallashtirilgan holda unique; ishlatilayotgan dastur — 409 |
| GET / POST | `/software-unrecognized`, `/software/{id}/adopt` `{name}` | katalogda yo'q nomlar (qurilmalar soni bilan) / shu nomdagi barcha yozuvlarni katalogga bog'lash (`software.manage`) |
| GET/POST/PUT/DELETE | `/software-licenses` | o'qish — doira; yozish — `software.manage`. Kalit faqat `license_key_masked`; `used`, `free`; bog'langan litsenziyani o'chirish — 409; o'rinni banddan kam qilish — 422 |
| POST | `/software-licenses/{id}/reveal` | `software.manage` — ochiq kalit, audit jurnaliga `secret_revealed` |
| GET | `/assets/{id}/software` | qurilma dasturlari, antivirus (`effective`), agent |
| POST / PATCH / DELETE | `/assets/{id}/software`, `/software-installations/{id}` | `software.install` — `{software_id \| name, version, vendor, installed_at, license_id}`; bo'sh o'rin bo'lmasa 422 |
| PUT | `/assets/{id}/antivirus` | `software.install` — `{software_id (antivirus), product_name, state: installed\|not_installed, definitions_updated_at, last_scan_at}` |
| GET/POST/PUT/DELETE | `/software-policies` | o'qish — umumiy + doiradagi tashkilotlar; yozish — `software.manage`. `organization_id` NULL (barcha tashkilotlar) faqat cheklovsiz foydalanuvchi uchun; boshqalar tashkilot tanlaydi (quyi tashkilotlari bilan) — umumiy siyosatni o'zgartirish 403, doiradan tashqarisi 404 |
| GET | `/software-compliance`, `/software-compliance/devices?filter[problem]=missing_required\|unprotected\|outdated` | tashkilotlar kesimida qurilmalar, antivirus holatlari, majburiy dasturi yo'qlar |
| POST | `/software-imports` `{type: installations\|antivirus, file}` | `software.install` — CSV (`,` yoki `;`), ≤ 5000 qator; javob `{total, imported, errors: [{line, message}]}` |
| GET | `/device-agents`, `/device-agents/{id}/checkins` | doiradagi qurilmalar agentlari va oxirgi 50 ta check-in |
| POST | `/assets/{id}/agent`, `/device-agents/{id}/tokens`, `/device-agents/{id}/revoke` | `software.agents` — javobdagi `token` faqat shu safar ko'rsatiladi; yangi token eskisini bekor qiladi |
| POST | `/exports` `{type: "software_compliance" \| "software_installations" \| "software_licenses"}` | `software.export` (litsenziya kaliti hech qachon eksport qilinmaydi) |

### Agent API

```http
POST /api/v1/agent/checkin HTTP/1.1
Authorization: Bearer atg_...
Content-Type: application/json

{
  "platform": "Windows 11 Pro", "agent_version": "1.0.0",
  "software": [{"name": "Microsoft Office 2021", "version": "16.0", "vendor": "Microsoft", "installed_at": "2024-02-01"}],
  "antivirus": {"product": "Kaspersky Endpoint Security", "state": "installed",
                "definitions_updated_at": "2026-11-08T03:00:00Z", "last_scan_at": "2026-11-07T22:00:00Z"}
}
```

Token qaysi qurilmaga berilgan bo'lsa, faqat o'sha qurilma yangilanadi. Agent manbali, endi kelmagan yozuvlar o'chadi;
qo'lda yoki CSV bilan kiritilganlar qoladi. Noto'g'ri yoki bekor qilingan token — 401, tokenga daqiqasiga 30 so'rov
(429). Har bir urinish `agent_checkins` ga yoziladi. Antivirus sanalari kelajakda (1 kundan ortiq) bo'lsa — 422
(aks holda qurilma doim "yangilangan" ko'rinib qolardi); CSV importida bunday qator xato sifatida qaytadi.

## 12. 6-bosqich endpointlari (SIM-kartalar, xizmatlar va to'lovlar)

| Metod | Yo'l | Ruxsat / izoh |
|---|---|---|
| GET/POST/PUT/DELETE | `/service-providers` (`filter[mobile]`, `filter[active]`) | o'qish — istalgan `sim.*`/`services.*`; yozish — `sim.manage` yoki `services.manage`; ishlatilgan provayder — 409 |
| GET/POST/PUT/DELETE | `/sim-cards`, `/sim-cards/{id}` | o'qish — `sim.view/assign/manage` (doira); yozish — `sim.manage`. `filter[search]` (raqam qismi yoki to'liq ICCID), `filter[provider]`, `filter[status]`, `filter[organization]`, `filter[employee]`. Raqam `998XXXXXXXXX` ga keltiriladi; ICCID faqat `iccid_masked`; kartada `assignments`, `history`. O'chirish faqat ishlatilmagan omborda turgan karta uchun (aks holda 409) |
| POST | `/sim-cards/{id}/reveal` | `sim.manage` — ochiq ICCID, audit `secret_revealed` |
| POST | `/sim-cards/{id}/assign` `{employee_id?, department_id?, assigned_at?, note?}`, `/return` `{returned_at?}`, `/block` `{reason?}`, `/unblock` | `sim.assign` yoki `sim.manage`; xodim/bo'lim karta tashkilotidan; noto'g'ri holat — 422 `invalid_transition` |
| POST | `/sim-cards/{id}/transfer` `{organization_id}`, `/cancel` `{reason?}`, `/replace` `{iccid, reason?}` | `sim.manage`; ko'chirish faqat bo'sh (in_stock/returned) karta va doiradagi tashkilotga |
| GET | `/employees/{id}/sim-cards`, `/sim-cards/{id}/documents` | xodimdagi kartalar; kartaga bog'langan hujjatlar |
| POST | `/requisitions/{id}/allocations` `{item_id, type: "sim", sim_card_id}` | 4-bosqich ajratishi: faqat "sim" turidagi bandga, bo'sh va boshqa ochiq talabnomaga berilmagan karta; karta talabnoma tashkilotiga o'tadi |
| GET/POST/PUT/DELETE | `/service-contracts` (`filter[status]=active\|expired\|terminated\|expiring`), `/service-contracts/{id}/documents` | o'qish — `services.*` (doira); yozish — `services.manage`; xizmati bor shartnoma — 409 |
| GET/POST/PUT/DELETE | `/it-services` (`filter[type]`, `filter[provider]`, `filter[contract]`, `filter[active]`, `filter[search]`) | yozish — `services.manage`; shartnoma shu tashkilot va provayderniki bo'lishi shart; yaratilganda joriy va keyingi davr hisoblari ochiladi; hisob-faktura/to'lovi bor xizmat — 409 |
| GET | `/service-invoices` (`filter[status]` yoki `open`, `filter[period]=YYYY-MM`, `filter[organization]`, `filter[provider]`, `filter[type]`, `filter[service]`, `filter[due_from/due_to]`, `filter[approval]=pending\|approved`) | ro'yxat + `summary {planned, invoiced, paid}` |
| PUT | `/service-invoices/{id}` `{invoice_number, invoice_date, amount, due_date, note}` | `services.invoice`; summa to'langandan kam bo'lmaydi; summa o'zgarsa tasdiq bekor bo'ladi |
| POST | `/it-services/{id}/invoices` `{description, amount, due_date, invoice_date?, invoice_number?}` | `services.invoice` — qo'shimcha xarajat |
| POST | `/service-invoices/{id}/approve` | `services.approve` — markaziy tasdiq |
| POST / DELETE | `/service-invoices/{id}/payments` `{amount, paid_at, document_number?}`, `/service-payments/{id}` | `services.pay` — faqat tasdiqlangan hisob-fakturaga, qoldiqdan oshmaydi (422) |
| POST | `/service-invoices/{id}/file`, `/service-payments/{id}/file` | skan (`file`, SafeUpload); yuklab olish `/files/{uuid}` — xizmatlar bilan ishlaydigan, tashkilot doirasidagi foydalanuvchi |
| DELETE | `/service-invoices/{id}` | `services.manage` — faqat to'lovsiz qo'shimcha xarajat (davriy hisob — 409, xizmat bo'lmagan bo'lsa summa 0 kiritiladi) |
| GET | `/sim-report` (`filter[organization]`, `filter[provider]`) | tashkilot × operator: jami, holatlar, oylik to'lov |
| GET | `/service-costs?year=&group_by=organization\|provider\|type` | 12 oy bo'yicha reja / hisob-faktura / to'langan (davr boshlanish oyi bo'yicha) |
| POST | `/exports` `{type: "sim_cards" \| "service_invoices" \| "service_costs"}` | `sim.export` / `services.export` (ICCID faqat oxirgi 4 raqam) |

Har kuni 08:15 `services:billing`: davrlar ochiladi, holatlar sanaga ko'ra yangilanadi, eslatmalar (bir marta):
to'lov muddatiga `services.due_notice_days` (5) kun qolganda — tashkilotning o'z hisob-faktura/to'lov xodimlariga;
muddati o'tganda — ularga va markaziy tasdiqlovchilarga; shartnoma tugashiga 30 kun qolganda — `services.manage` egalariga.

## 13. Misol

```http
POST /api/v1/employees HTTP/1.1
Accept: application/json
Content-Type: application/json
X-XSRF-TOKEN: eyJpdiI6...

{
  "organization_id": 5,
  "department_id": 14,
  "last_name": "Karimov",
  "first_name": "Aziz",
  "middle_name": "Rustamovich",
  "position": "Muhandis-dasturchi",
  "personnel_number": "00412",
  "phone": "+998901234567",
  "status": "active",
  "hired_at": "2024-03-01"
}
```

```http
HTTP/1.1 201 Created
X-Request-Id: 0f8b2c1e-...

{
  "data": {
    "id": 231,
    "full_name": "Karimov Aziz Rustamovich",
    "organization": { "id": 5, "name": "Xorazm GTS" },
    "department": { "id": 14, "name": "AT bo'limi" },
    "status": "active",
    "can": { "update": true, "delete": true },
    "created_at": "2026-11-03T06:30:00Z"
  }
}
```
