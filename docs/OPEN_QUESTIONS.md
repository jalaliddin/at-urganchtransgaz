# AT.urtg.uz — Ochiq savollar

Topshiriqda aniq ko'rsatilmagan biznes qoidalari. Har biri bo'yicha **xavfsiz
standart yechim** tanlangan va kodda qo'llangan; buyurtmachi tasdiqlagach yoki
o'zgartirgach, bu yerda belgilanadi.

| Belgi | Ma'nosi |
|---|---|
| ❓ | Javob kutilmoqda (standart yechim ishlatilmoqda) |
| ✅ | Tasdiqlangan |

## Tasdiqlangan qarorlar

| # | Savol | Qaror |
|---|---|---|
| D1 | SPA autentifikatsiyasi | ✅ Sanctum cookie sessiyasi (HttpOnly), bearer token faqat integratsiyalar uchun |
| D2 | Rol va tashkilot bog'liqligi | ✅ Rol — global; ma'lumot doirasi — `user_organization_access` |
| D3 | Login identifikatori | ✅ Username; email ixtiyoriy (parol tiklash uchun) |
| D4 | Laravel versiyasi | ✅ Laravel 13 (joriy barqaror; PHP 8.3 bilan mos) |

## 1-bosqich bo'yicha ochiq savollar

| # | Savol | Standart yechim (hozir ishlaydi) | Holat |
|---|---|---|---|
| Q1 | Quyi tashkilot AT mas'uli o'z tashkiloti uchun **tizim foydalanuvchilarini** yarata oladimi? | Yo'q. U xodimlar va bo'limlarni yuritadi; foydalanuvchi hisoblarini markaziy AT yaratadi. Kerak bo'lsa rolga `users.*` ruxsatlari qo'shiladi (imtiyoz oshirish himoyasi saqlanadi) | ❓ |
| Q2 | Audit loglar qancha muddat saqlanadi? | Cheksiz, avtomatik o'chirish yo'q | ❓ |
| Q3 | Tashkilot turlari klassifikatsiyasi (Markaziy apparat, MGQB, GTS, ...)? | `head_office`, `subsidiary`, `branch`, `other` + erkin nom. Kerak bo'lsa ma'lumotnomaga aylantiriladi | ❓ |
| Q4 | Tashkilot faolsizlantirilganda uning foydalanuvchilari tizimga kira oladimi? | Ha (tarixiy ma'lumotlarni ko'rish uchun); faqat yangi yozuv qo'shish bloklanadi | ❓ |
| Q5 | Parol siyosati (uzunlik, murakkablik, amal qilish muddati) | Kamida 10 belgi, harf + raqam; muddat cheklanmagan (`0`). Sozlamalardan o'zgartiriladi | ❓ |
| Q6 | Sessiya davomiyligi | 120 daqiqa harakatsizlik, "Meni eslab qol" — 30 kun | ❓ |
| Q7 | SMTP server mavjudmi (parol tiklash xatlari uchun)? | Sozlanmagan bo'lsa — administrator vaqtinchalik parol beradi | ❓ |
| Q8 | Xodim holatlari ro'yxati yetarlimi? | `active` (faol), `on_leave` (ta'tilda), `business_trip` (xizmat safarida), `dismissed` (ishdan bo'shagan) | ❓ |
| Q9 | Tabel raqami unikalligi | Tashkilot ichida unikal | ❓ |
| Q10 | Bitta xodim bir nechta tashkilot uchun AT mas'ul bo'la oladimi? | Ha; har bir tashkilotda bitta "asosiy" mas'ul | ❓ |
| Q11 | Markaziy AT xodimi barcha tashkilotlar xodimlarini qo'sha/tahrirlay oladimi? | Ha (`employees.create/update`), o'chira olmaydi | ❓ |
| Q12 | Active Directory / LDAP orqali kirish (SSO) kerakmi? | Hozircha lokal hisoblar; arxitektura keyin qo'shishga tayyor | ❓ |
| Q13 | Xodim ishdan bo'shatilganda | Bog'langan foydalanuvchi avtomatik faolsizlantiriladi va sessiyalari yopiladi; aktivlarni qaytarish — 3-bosqich | ❓ |

## Infratuzilma bo'yicha savollar

| # | Savol | Standart yechim | Holat |
|---|---|---|---|
| I1 | `at.urtg.uz` qaysi serverda va TLS sertifikat qayerdan? | nginx o'zi TLS'ni tugatadi (`docker/nginx/certs/`), Let's Encrypt yoki korporativ CA. Yuqorida boshqa reverse proxy bo'lsa — `TRUSTED_PROXIES` orqali | ❓ |
| I2 | Shaxsiy ma'lumotlar (xodim telefonlari, emaillari) — server O'zbekiston hududida joylashganmi? | "Shaxsiy ma'lumotlar to'g'risida"gi qonun talabi bo'yicha server O'zbekistonda bo'lishi kerak deb hisoblanadi | ❓ |
| I3 | Backup saqlash muddati va joyi | Kunlik DB + fayllar, 14 kun lokal; tashqi nusxa joyi aniqlanishi kerak | ❓ |
