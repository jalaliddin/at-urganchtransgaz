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
| D5 | Helpdesk SLA hisoblash | ✅ Ish vaqti bo'yicha: Du–Ju 09:00–18:00, tushlik 13:00–14:00, Asia/Tashkent, bayramlar sozlamada (2026-11-03) |

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

## 2-bosqich (Helpdesk) bo'yicha ochiq savollar

| # | Savol | Standart yechim (amalda) | Holat |
|---|---|---|---|
| Q14 | Bayram va qo'shimcha dam olish kunlari ro'yxatini kim kiritadi? | Helpdesk sozlamalarida `helpdesk.manage` ruxsatli xodim har yil kiritadi; standart ro'yxat bo'sh | ❓ |
| Q15 | SLA me'yorlari (javob / hal qilish, ish daqiqalari) | Kritik 30/240, yuqori 60/480, normal 120/1440, past 480/2400; kategoriya bo'yicha alohida qoida qo'shish mumkin | ❓ |
| Q16 | Hal qilingan arizani avtomatik yopish va qayta ochish muddati | 3 ish kunidan keyin avtomatik yopiladi; murojaatchi 7 kun ichida qayta ochishi mumkin | ❓ |
| Q17 | Oddiy xodim "kritik" ustuvorlikni tanlay oladimi? | Ha; bajaruvchi keyin o'zgartiradi (SLA qayta hisoblanadi) | ❓ |
| Q18 | Yangi ariza haqida kim xabar oladi? | `helpdesk.assign` ruxsati bor va ariza tashkilotiga kirishi bor barcha faol foydalanuvchilar (quyi tashkilot AT mas'uli, markaziy AT rahbari) | ❓ |
| Q19 | Email yuborish uchun SMTP server | `.env` dagi `MAIL_*`; sozlanmaguncha xatlar jurnalga yoziladi, tizim ichidagi bildirishnomalar ishlaydi | ❓ |
| Q20 | Fayl cheklovlari | 10 MB (sozlanadi, ko'pi bilan 20 MB), bir yuklashda 5 tagacha; rasm, PDF, Office, ODF, TXT/CSV, ZIP/RAR/7Z; skript/HTML/SVG/exe taqiqlangan | ❓ |
| Q21 | Eksport fayllari qancha saqlanadi? | 7 kun; PDF 2000 qatordan oshsa Excel tavsiya qilinadi | ❓ |
| Q22 | Ichki izohni kim ko'radi? | Faqat ariza tashkilotiga kirishi bor `helpdesk.process` egalari (murojaatchi va kuzatuvchilar ko'rmaydi) | ❓ |

## 3-bosqich (IT aktivlar) bo'yicha ochiq savollar

| # | Savol | Standart yechim (amalda) | Holat |
|---|---|---|---|
| Q23 | Inventar raqami kim tomonidan beriladi? | Buxgalteriya raqami qo'lda kiritiladi; bo'sh bo'lsa tizim `AT-000001` beradi | ❓ |
| Q24 | Hisobdan chiqarishni kim tasdiqlaydi? | `assets.dispose` ruxsati — standart bo'yicha markaziy AT rahbari | ❓ |
| Q25 | Inventarizatsiyada topilmagan aktiv avtomatik "yo'qolgan" bo'ladimi? | Yo'q — hisobotda ko'rsatiladi, holatni mas'ul xodim o'zi o'zgartiradi | ❓ |
| Q26 | Ko'chirishda qabul qiluvchi tomon aktivni kimga biriktiradi? | Qabul qilingach aktiv "omborda" holatida, bo'lim/joylashuv/MJSh bo'sh — qabul qiluvchi o'zi to'ldiradi | ❓ |
| Q27 | Kafolat ogohlantirishi necha kun oldin? | 30 kun oldin, har kuni 08:00 da, har aktiv uchun bir marta | ❓ |
| Q28 | Ishdan bo'shagan xodim aktivlari | Avtomatik qaytarilmaydi; tashkilot AT mas'ullariga ro'yxat bilan xabar yuboriladi | ❓ |
| Q29 | Dalolatnoma shakli | Tizim shakli (o'zbek tilida): tashkilotlar, aktivlar ro'yxati, imzo joylari. Korxona shabloni bo'lsa, almashtirish mumkin | ❓ |

## 4-bosqich (Ombor va talabnomalar) bo'yicha ochiq savollar

| # | Savol | Standart yechim (amalda) | Holat |
|---|---|---|---|
| Q30 | Ombor va katalogni kim yuritadi? | `warehouse.manage` — standart bo'yicha markaziy AT rahbari; quyi tashkilot omborini ham markaz ochadi (rollar sozlanadi) | ❓ |
| Q31 | "Saqlash joyi" darajasi | Ombor = saqlash joyi (+ 3-bosqich joylashuvi); javon/katak darajasi yuritilmaydi | ❓ |
| Q32 | Material baholash usuli | Partiya ichida o'rtacha narx, chiqim FIFO (eng yaqin muddat, keyin eng eski partiya); har bir harakatda narx saqlanadi | ❓ |
| Q33 | Inventarizatsiya vaqtida kirim-chiqim | Taqiqlanadi (409) — sanoq yopilguncha yoki bekor qilinguncha; hisobdagi miqdor sanoqchiga ko'rinadi | ❓ |
| Q34 | Minimal qoldiq va muddat ogohlantirishi kimga? | Ombor tashkilotidagi `warehouse.receive` egalari va ombor mas'uli; muddat — 30 kun oldin, har kuni 08:05 | ❓ |
| Q35 | Tashkilot rahbari tasdig'i | Standart yoqilgan (`requisitions.head_approval`); tashkilotda tasdiqlovchi bo'lmasa bosqich o'tkazib yuboriladi (tarixda qayd) | ❓ |
| Q36 | Markaziy AT rahbari tashkilot bosqichida tasdiqlay oladimi? | Yo'q — `requisitions.approve_org` faqat quyi tashkilot rahbarida; markaziy rahbar o'z bosqichida qaror qiladi | ❓ |
| Q37 | Qabulni kim tasdiqlaydi? | Talabnoma yaratuvchisi yoki o'sha tashkilotning AT mas'uli (`requisitions.create`, asosiy tashkiloti shu) | ❓ |
| Q38 | Noto'g'ri ajratishni bekor qilish | Ajratish bekor qilinmaydi; tuzatish ombor qaytarish hujjati yoki aktiv ko'chirishi orqali | ❓ |
| Q39 | Ombordan ajratish: chiqim yoki ko'chirish? | Standart — so'ragan tashkilotga chiqim; ajratuvchi uning omborini tanlasa — ko'chirish | ❓ |

## 5-bosqich (Dasturlar, antivirus) bo'yicha ochiq savollar

| # | Savol | Standart yechim (amalda) | Holat |
|---|---|---|---|
| Q40 | Qaysi qurilmalar dastur/antivirus nazoratida? | Kompyuter va server toifalari (`config/software.php`) + siyosatda ko'rsatilgan toifalar; hisobdan chiqarilgan va yo'qolganlar kirmaydi | ❓ |
| Q41 | Antivirus "eskirgan" qachon? | Bazalar 7 kundan eski (sozlamada `software.av_outdated_days`); hisobot 30 kundan eski bo'lsa holat noma'lum | ❓ |
| Q42 | Litsenziyani kim yuritadi va kalitni kim ko'radi? | `software.manage` (markaziy AT rahbari); kalit shifrlangan, ochish audit qilinadi | ❓ |
| Q43 | Agent qaysi dasturlarni yuboradi va kim o'rnatadi? | Agent/integratsiya (Windows agent, AD/GPO, antivirus konsoli) keyin ulanadi; API tayyor, token qurilmaga bog'langan | ❓ |
| Q44 | Litsenziya muddati ogohlantirishi | 30 kun oldin, har kuni 08:10, har litsenziya va sana uchun bir marta | ❓ |
| Q45 | ICMP tarmoq monitoringi | Foydalanuvchi qarori (2026-11-08): kerak emas, qilinmaydi | ✅ |

## 6-bosqich (SIM, xizmatlar, to'lovlar) bo'yicha ochiq savollar

| # | Savol | Standart yechim (amalda) | Holat |
|---|---|---|---|
| Q46 | SIM-kartani kim ro'yxatga oladi va ko'chiradi? | `sim.manage` (markaziy AT); quyi tashkilot AT mas'uli xodimga biriktiradi, qaytaradi, vaqtincha bloklaydi (`sim.assign`) | ❓ |
| Q47 | SIM so'rovi qanday yuboriladi? | 4-bosqich talabnomasi ("SIM-karta" bandi); ajratilganda karta darhol so'ragan tashkilotga o'tadi (alohida qabul bosqichisiz) | ❓ |
| Q48 | ICCID kimga ko'rinadi? | Hammaga oxirgi 4 raqam; to'liq — `sim.manage`, ochish audit qilinadi | ❓ |
| Q49 | Hisob davri va to'lov muddati | Kalendar oy/chorak/yil; muddat — davrning birinchi oyidagi `due_day` (standart 10); har hisobda qo'lda o'zgartiriladi | ❓ |
| Q50 | Xizmat o'rtada qo'shilsa | Kuzatish boshlanishidan (boshlanish sanasi yoki ro'yxatga olingan kun) oldin muddati o'tgan davr ochilmaydi; kerak bo'lsa qo'shimcha xarajat sifatida kiritiladi | ❓ |
| Q51 | Markaziy tasdiq | Kiritilgan hisob-fakturani `services.approve` (markaziy AT rahbari) tasdiqlaydi; to'lov faqat tasdiqlangandan keyin; summa o'zgarsa qayta tasdiq | ❓ |
| Q52 | Oldindan (avans) to'lov | Qabul qilinmaydi: to'lov qoldiqdan oshmaydi | ❓ |
| Q53 | To'lov eslatmalari kimga? | Muddatga 5 kun qolganda — tashkilotning o'z hisob-faktura/to'lov xodimlari; muddati o'tganda — ular va markaziy tasdiqlovchilar | ❓ |
| Q54 | Xarajatlar qaysi oyga yoziladi? | Hisob davri boshlangan oyga (reja, hisob-faktura va to'lov bir xil qoidada) | ❓ |

## Infratuzilma bo'yicha savollar

| # | Savol | Standart yechim | Holat |
|---|---|---|---|
| I1 | `at.urtg.uz` qaysi serverda va TLS sertifikat qayerdan? | nginx o'zi TLS'ni tugatadi (`docker/nginx/certs/`), Let's Encrypt yoki korporativ CA. Yuqorida boshqa reverse proxy bo'lsa — `TRUSTED_PROXIES` orqali | ❓ |
| I2 | Shaxsiy ma'lumotlar (xodim telefonlari, emaillari) — server O'zbekiston hududida joylashganmi? | "Shaxsiy ma'lumotlar to'g'risida"gi qonun talabi bo'yicha server O'zbekistonda bo'lishi kerak deb hisoblanadi | ❓ |
| I3 | Backup saqlash muddati va joyi | Kunlik DB + fayllar, 14 kun lokal; tashqi nusxa joyi aniqlanishi kerak | ❓ |
