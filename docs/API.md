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
| 409 | `conflict` | Biznes qoidasi buzildi (masalan, bolalari bor tashkilotni o'chirish) |
| 419 | `csrf_token_mismatch` | CSRF token eskirgan |
| 422 | `validation_error` | Validatsiya |
| 429 | `too_many_requests` | Rate limit (`Retry-After` header) |
| 500 | `server_error` | Ichki xato — tafsilotlarsiz, `request_id` bilan |

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

## 8. Misol

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
