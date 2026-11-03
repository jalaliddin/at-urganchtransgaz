# AT.urtg.uz — Yo'l xaritasi

Har bir bosqich **ishlaydigan holatda** yakunlanadi va quyidagi tekshiruvdan o'tadi:

1. Migratsiyalar toza bazada ishlaydi.
2. Backend testlari (PHPUnit), Pint, Larastan — muvaffaqiyatli.
3. Frontend: `tsc`, ESLint, Vitest, production build — muvaffaqiyatli.
4. `docker compose up -d --build` — barcha servislar `healthy`.
5. README va tegishli `docs/` hujjatlari yangilangan.
6. Bajarilgan va qolgan ishlar ro'yxati hisobotda.

Har bir modul: migratsiya + model + validatsiya + policy (tashkilot doirasi bilan) +
service + API + frontend sahifalar + testlar (shu jumladan tashkilotlararo kirish testlari).

| Belgi | Ma'nosi |
|---|---|
| ✅ | Bajarildi |
| 🔄 | Jarayonda |
| ⏳ | Rejalashtirilgan |

---

## 0-bosqich — Tahlil va arxitektura 🔄

- Repozitoriya tahlili (bo'sh loyiha, faqat topshiriq fayli bor edi)
- `README.md`, `docs/ARCHITECTURE.md`, `docs/ROADMAP.md`, `docs/DATABASE.md`,
  `docs/API.md`, `docs/OPEN_QUESTIONS.md`
- Papkalar tuzilmasi, Docker Compose va nginx konfiguratsiyasi
- ERD va migratsiyalar rejasi, RBAC va tashkilot doirasi strategiyasi

## 1-bosqich — Foundation 🔄

**Backend**
- Laravel 13 loyihasi, `/api/v1`, yagona JSON javob va xatolar formati, request ID
- Sanctum SPA auth: login/logout, me, parol almashtirish, parolni tiklash (email),
  majburiy parol almashtirish, faolsiz foydalanuvchini bloklash, throttle
- Tashkilotlar (daraxt, ko'chirish), bo'limlar, xodimlar, AT mas'ullari
- Foydalanuvchilar: yaratish, tahrirlash, faolsizlantirish, parolni tiklash,
  rollar va tashkilot doirasini boshqarish (imtiyoz oshirishdan himoya bilan)
- RBAC: ruxsatlar reyestri, 8 ta boshlang'ich rol, rollarni boshqarish
- Tashkilot doirasi: global scope + policy + validatsiya
- Audit log (model hodisalari + auth hodisalari), o'zgarmas
- Tizim sozlamalari, ommaviy sozlamalar
- Tizim ichidagi bildirishnomalar (asos), global qidiruv, dashboard API
- Xodim ishdan bo'shatilganda foydalanuvchini faolsizlantirish (event)
- `app:create-super-admin` CLI, idempotent seederlar, demo ma'lumotlar (faqat dev)
- OpenAPI (Scramble)

**Frontend**
- Vite + React + TS strict + Mantine, i18n (uz, ru tayyor), light/dark theme
- Layout: yig'iladigan sidebar / mobil drawer, header (breadcrumb, qidiruv,
  bildirishnomalar, tashkilot konteksti, profil)
- Login, parolni tiklash, majburiy parol almashtirish sahifalari
- Dashboard (faqat real ko'rsatkichlar), tashkilotlar daraxti, bo'limlar,
  xodimlar, foydalanuvchilar, rollar, audit log, sozlamalar, profil
- Umumiy DataTable, empty/loading/error holatlari, ConfirmDialog, StatusBadge

**Infra**
- Docker Compose: nginx, frontend, backend, queue-worker, scheduler, mysql, redis,
  mailpit (dev); healthcheck, restart, log rotation; dev/prod ajratish
- `.env.example`, `scripts/init-env.sh`, backup/restore skriptlari, TLS yo'riqnomasi

## 2-bosqich — Helpdesk ⏳

- Kategoriyalar, SLA siyosatlari (ustuvorlik × kategoriya), ariza raqami generatori
- Ariza: yaratish (mobilga mos), status workflow, mas'ul tayinlash/qayta tayinlash
- Ichki va ochiq izohlar, fayl ilovalari (private storage, ruxsat bilan yuklab olish)
- Tarix, takroriy muammolarni bog'lash, qayta ochish, baholash
- SLA hisoblash (scheduler), kechikish belgisi, rangli indikatorlar
- Bildirishnomalar: yangi ariza, tayinlash, izoh, status o'zgarishi
- Hujjatlar moduli asosi (`documents`, `document_links`, versiyalar)
- Eksport infratuzilmasi: Excel/PDF, katta hisobotlar queue orqali
- Helpdesk dashboard vidjetlari va hisobotlari

## 3-bosqich — IT aktivlar va CMDB ⏳

- Aktiv toifalari, ishlab chiqaruvchi/model ma'lumotnomalari, joylashuvlar
- Aktiv kartasi (inventar raqami, serial, MAC, IP, kafolat, qiymat, fayllar)
- Xodimga/tashkilotga biriktirish va qaytarish, harakatlar tarixi
- Tashkilotlararo ko'chirish (topshirish-qabul dalolatnomasi)
- Inventarizatsiya sessiyalari (faktik vs hisobdagi), QR/barcode
- Ta'mir tarixi, kafolat ogohlantirishlari, hisobdan chiqarish
- Xodim ishdan bo'shaganda aktivlarni qaytarish jarayoni

## 4-bosqich — Ombor va talabnomalar ⏳

- Material katalogi, o'lchov birliklari, omborlar
- Kirim, chiqim, qaytarish, omborlararo ko'chirish — tranzaksiya + row lock,
  manfiy qoldiqqa yo'l qo'yilmaydi; partiya/yaroqlilik muddati; minimal qoldiq
- Talabnomalar: workflow (draft → ... → yopilgan), tasdiqlash bosqichlari,
  resurs ajratish → aktiv/material hisobiga avtomatik bog'lanish, qabul tasdig'i

## 5-bosqich — Monitoring va dasturiy ta'minot ⏳

- ICMP monitoring worker (alohida servis, `NET_RAW`, allowlist, parallel, rate limit)
- Hodisalar (offline → ochish, online → yopish, deduplikatsiya), availability, latency grafigi
- Dasturlar katalogi, litsenziyalar, o'rnatilgan dasturlar, antivirus holati
- Agent integratsiyasi modeli (`device_agents`, `agent_tokens`, `agent_checkins`), CSV import

## 6-bosqich — SIM, xizmatlar va to'lovlar ⏳

- Operatorlar, SIM reyestri (ICCID himoyasi), SIM talabnomalari va tasdiqlash
- Provayderlar, shartnomalar, xizmatlar, hisob-fakturalar, to'lovlar, muddat eslatmalari
- Xarajatlar tahlili va hisobotlar

## 7-bosqich — Kanban, printerlar va qolgan modullar ⏳

- Kanban: boardlar, sozlanadigan ustunlar, drag-and-drop, checklist, yorliqlar,
  takrorlanuvchi vazifalar, yuklama ko'rinishi, Helpdesk arizasidan topshiriq
- Printerlar va kartrijlar (ombor bilan integratsiya), sarf tahlili
- Knowledge Base, Change/Problem Management, rejali texnik xizmat,
  xavfsizlik hodisalari, backup jurnali, tashkilot AT pasporti
- Kengaytirilgan hisobotlar va analitika

## Keyingi (bosqichlardan tashqari)

- MFA/2FA (TOTP), Telegram/SMS bildirishnomalar, Active Directory integratsiyasi,
  rus tili tarjimalari
