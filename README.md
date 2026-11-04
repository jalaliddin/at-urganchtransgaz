# AT.urtg.uz — Axborot texnologiyalari xizmatini boshqarish tizimi

"Urganchtransgaz" MCHJ markaziy apparati va quyi tashkilotlarining AT xizmati uchun yagona
platforma: tashkiliy tuzilma, xodimlar, foydalanuvchilar va ruxsatlar, audit; keyingi
bosqichlarda Helpdesk, IT-aktivlar (CMDB), ombor, monitoring va boshqalar
([yo'l xaritasi](docs/ROADMAP.md)).

| Qatlam | Texnologiya |
|---|---|
| Backend | PHP 8.3, Laravel 13, Sanctum (SPA cookie), MySQL 8.4, Redis 7 |
| Frontend | React 19, TypeScript (strict), Vite, Mantine 9, TanStack Query |
| Infratuzilma | Docker Compose: nginx, frontend, backend (PHP-FPM), queue-worker, scheduler, mysql, redis |

Hujjatlar: [arxitektura](docs/ARCHITECTURE.md) · [ma'lumotlar bazasi](docs/DATABASE.md) ·
[API](docs/API.md) · [yo'l xaritasi](docs/ROADMAP.md) · [ochiq savollar](docs/OPEN_QUESTIONS.md).
Ishga tushirilgan tizimda interaktiv API hujjati: `/docs/api` (lokal muhitda ochiq,
boshqa muhitlarda faqat Super Admin uchun).

---

## 1. Talablar

- Docker 24+ va Docker Compose v2 (`docker compose version`)
- `make`, `openssl`, `bash`
- Bo'sh portlar: `8095` (ilova), `8025` (mailpit, faqat dev), `33095` (MySQL, faqat dev va faqat `127.0.0.1`)

PHP, Composer yoki Node.js hostga o'rnatilishi shart emas — hammasi konteynerlarda ishlaydi.

## 2. Tez boshlash (development)

```bash
./scripts/init-env.sh           # .env yaratadi va barcha maxfiy qiymatlarni tasodifiy generatsiya qiladi
docker compose up -d --build    # birinchi marta: image'lar, composer va npm o'rnatiladi (bir necha daqiqa)
make migrate                    # jadvallar
make seed                       # rollar/ruxsatlar + demo ma'lumotlar (faqat dev)
```

Ilova: <http://localhost:8095> · Xatlar (parol tiklash): <http://localhost:8025>

Demo hisoblar (paroli — `.env` dagi `DEMO_PASSWORD`):

| Login | Rol | Kirish doirasi |
|---|---|---|
| `demo.super` | Super Admin | Hammasi |
| `demo.head` | Markaziy AT rahbari | Markaziy apparat + barcha quyi tashkilotlar |
| `demo.staff` | Markaziy AT xodimi | Markaziy apparat + barcha quyi tashkilotlar |
| `demo.itresp` | Quyi tashkilot AT mas'uli | Xorazm GTS + filiali |
| `demo.orghead` | Quyi tashkilot rahbari | Xorazm GTS + filiali |
| `demo.employee` | Oddiy xodim | Xorazm GTS |
| `demo.accountant` | Buxgalter | Urganch MGQB |
| `demo.auditor` | Auditor | Hammasi (faqat o'qish) |

Demo ma'lumotlar faqat `local`/`development`/`testing` muhitlarida yaratiladi; seederlar
qayta ishga tushirilganda dublikat yaratmaydi. Toza bazadan qayta boshlash: `make fresh`.

## 3. Birinchi Super Admin

Kodda standart parol yo'q. Birinchi administrator CLI orqali yaratiladi va birinchi kirishda
parolni almashtirishga majbur bo'ladi:

```bash
docker compose exec backend php artisan app:create-super-admin            # interaktiv
# yoki .env dagi SUPER_ADMIN_USERNAME / SUPER_ADMIN_PASSWORD / SUPER_ADMIN_NAME / SUPER_ADMIN_EMAIL dan:
docker compose exec backend php artisan app:create-super-admin --from-env
```

Buyruq idempotent: login allaqachon mavjud bo'lsa, faqat Super Admin roli tekshiriladi.
`--from-env` dan keyin `.env` dagi `SUPER_ADMIN_PASSWORD` ni o'chirib qo'ying.

## 4. Muhitlar

Muhit `.env` dagi `COMPOSE_FILE` bilan tanlanadi, shuning uchun har qanday muhitda buyruq bir xil:
`docker compose up -d --build`.

| Muhit | `COMPOSE_FILE` | Xususiyati |
|---|---|---|
| development | `compose.yaml:compose.dev.yaml` | Kod bind-mount, Vite HMR, mailpit, test DB, MySQL `127.0.0.1:33095` da |
| staging / production | `compose.yaml:compose.prod.yaml` | Kod image ichida, dev dependency yo'q, OPcache, TLS, MySQL tashqariga ochilmaydi, migratsiyalar avtomatik |

### Production ga o'rnatish

1. Serverga repozitoriyani joylang va `./scripts/init-env.sh` ni ishga tushiring.
2. `.env` ni tahrirlang:

   ```dotenv
   COMPOSE_FILE=compose.yaml:compose.prod.yaml
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://at.urtg.uz
   NGINX_SERVER_NAME=at.urtg.uz
   SANCTUM_STATEFUL_DOMAINS=at.urtg.uz
   SESSION_SECURE_COOKIE=true
   LOG_LEVEL=info
   MAIL_MAILER=smtp           # va MAIL_HOST/PORT/USERNAME/PASSWORD — korporativ pochta serveri
   ```

3. TLS sertifikatini joylang (5-bo'lim), so'ng `docker compose up -d --build`.
4. `docker compose exec backend php artisan db:seed --force` — faqat rollar va ruxsatlar (demo yo'q).
5. Birinchi Super Admin — 3-bo'lim.

Production nazorat ro'yxati:

- [ ] `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`
- [ ] `.env` faqat server egasi o'qiy oladi (`chmod 600 .env`) va Git'da yo'q
- [ ] Ilova MySQL'ga `DB_USERNAME` (root emas) bilan ulanadi; MySQL porti ochilmagan
- [ ] TLS sertifikati haqiqiy (o'z-o'zidan imzolangan emas), HTTP → HTTPS yo'naltirilgan
- [ ] Kunlik backup ishlaydi va restore sinovdan o'tkazilgan (6-bo'lim)
- [ ] Ixtiyoriy qo'shimcha himoya: audit jurnalini DB darajasida o'zgartirib bo'lmas qilish:
      `REVOKE UPDATE, DELETE ON at_urtg.audit_logs FROM 'at_urtg'@'%';`

## 5. TLS

nginx `docker/nginx/certs/` (yoki `TLS_CERT_DIR`) dan `fullchain.pem` va `privkey.pem` ni o'qiydi.

**Let's Encrypt (webroot):** `/.well-known/acme-challenge/` `acme-webroot` volume'dan beriladi.

```bash
./scripts/selfsigned-cert.sh                      # nginx ishga tushishi uchun vaqtinchalik sertifikat
docker compose up -d
docker run --rm -v aturtg_acme-webroot:/var/www/acme -v "$PWD/letsencrypt:/etc/letsencrypt" \
  certbot/certbot certonly --webroot -w /var/www/acme -d at.urtg.uz --agree-tos -m admin@urtg.uz
cp letsencrypt/live/at.urtg.uz/fullchain.pem letsencrypt/live/at.urtg.uz/privkey.pem docker/nginx/certs/
docker compose exec nginx nginx -s reload
```

Yangilash uchun xuddi shu `certbot renew` + nusxa + `nginx -s reload` ni cron'ga qo'ying.
**Korporativ CA:** berilgan sertifikat zanjiri va kalitni shu fayl nomlari bilan joylang.
HTTPS standart bo'lmagan portda bo'lsa, `HTTPS_PORT=8443` va `HTTPS_PORT_SUFFIX=:8443`.

### Mavjud reverse proxy ortida (80/443 band bo'lsa)

Serverda 80/443 portlarni boshqa web-server (masalan, host nginx) egallagan va TLS'ni o'sha tugatsa,
ilova nginx'ini faqat lokal HTTP portda ishga tushiring. `.env`:

```
NGINX_TEMPLATE=proxied
HTTP_PORT=127.0.0.1:8082
HTTPS_PORT=127.0.0.1:8443        # bu rejimda ishlatilmaydi, lekin bo'sh port bo'lishi kerak
TRUSTED_PROXIES=172.16.0.0/12    # Docker tarmoqlari: haqiqiy IP va https sxemasi shu orqali o'tadi
SESSION_SECURE_COOKIE=true
```

Host nginx (TLS sertifikati shu yerda):

```nginx
server {
    listen 443 ssl http2;
    server_name at.urtg.uz;
    ssl_certificate     /etc/ssl/at.urtg.uz/fullchain.pem;
    ssl_certificate_key /etc/ssl/at.urtg.uz/privkey.pem;
    client_max_body_size 110m;              # 5 ta fayl × 20 MB

    location / {
        proxy_pass http://127.0.0.1:8082;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 120s;
    }
}
server { listen 80; server_name at.urtg.uz; return 301 https://$host$request_uri; }
```

`docker compose up -d`, so'ng `sudo nginx -t && sudo systemctl reload nginx`. Docker boshqa manzil
diapazonini ishlatsa (`docker network inspect aturtg_default` → `Subnet`), `TRUSTED_PROXIES` ni shunga moslang.

## 6. Backup va restore

```bash
./scripts/backup.sh                  # backups/<UTC vaqt>/{db.sql.gz, storage.tar.gz, SHA256SUMS}
./scripts/restore.sh backups/20261103T120000Z   # joriy baza va fayllar USTIDAN yozadi (tasdiq so'raydi)
```

- Backup ilova DB foydalanuvchisi bilan `mysqldump --single-transaction` qiladi (ish to'xtamaydi)
  va `storage/app` (yuklangan fayllar) ni arxivlaydi; `BACKUP_RETENTION_DAYS` (standart 14)
  kundan eski nusxalar o'chiriladi.
- Kunlik cron misoli: `30 2 * * * cd /opt/at.urtg.uz && ./scripts/backup.sh /srv/backups/at >> /var/log/at-backup.log 2>&1`
- Nusxalarni boshqa serverga ham ko'chiring (ochiq savol I3).
- **Restore sinovi (oyiga bir marta):** alohida serverda yoki `COMPOSE_PROJECT_NAME` boshqa
  bo'lgan nusxada `restore.sh` ni bajaring, tizimga kiring, so'nggi o'zgarishlar va fayllar
  joyida ekanini tekshiring.

## 7. Kundalik buyruqlar

| Buyruq | Vazifasi |
|---|---|
| `make up` / `make down` | Ishga tushirish / to'xtatish (ma'lumotlar saqlanadi) |
| `make ps` | Servislar holati (healthcheck bilan) |
| `make logs ARGS=backend` | Servis loglari (Docker tomonidan aylantiriladi: 5 × 10 MB) |
| `make shell` | Backend konteyneriga kirish |
| `make migrate` | Migratsiyalar |
| `make seed` | Seederlar (idempotent) |
| `make test-backend ARGS="--filter=Employee"` | PHPUnit (alohida `at_urtg_test` bazasida) |
| `make test-frontend` | Vitest |
| `make lint` | Pint, Larastan, `tsc`, ESLint |
| `make check` | Barcha testlar, linterlar va production build |

Yangilash: `git pull && docker compose up -d --build` (productionda migratsiyalar backend
ishga tushganda avtomatik bajariladi). Konteynerlarda Laravel loglari `stderr` ga yoziladi;
har bir javobdagi `X-Request-Id` loglar va audit yozuvlarini bog'laydi.

## 8. Tekshiruv

Butun oqimni (CSRF cookie → login → me → logout) nginx orqali tekshirish:

```bash
SMOKE_USERNAME=demo.head SMOKE_PASSWORD=... ./scripts/smoke.sh http://localhost:8095
SMOKE_INSECURE=1 SMOKE_USERNAME=... SMOKE_PASSWORD=... ./scripts/smoke.sh https://staging.at.urtg.uz
```

## 9. Loyiha tuzilmasi

```
backend/    Laravel API (/api/v1)
frontend/   React SPA
docker/     Dockerfile'lar va nginx konfiguratsiyasi
scripts/    init-env, backup, restore, smoke, selfsigned-cert
docs/       Arxitektura, DB, API, yo'l xaritasi, ochiq savollar
```

Batafsil — [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).
