# Claude Code uchun loyiha topshirig'i --- at.urtg.uz

## Axborot texnologiyalari xizmatini boshqarish, Helpdesk va IT-aktivlar platformasi

Sen tajribali **Solution Architect, Senior Laravel Backend Developer,
Senior React Developer, DevOps Engineer va UI/UX Designer** sifatida
ishlaysan. Vazifang --- `at.urtg.uz` domenida ishlaydigan,
"Urganchtransgaz" MCHJ markaziy apparati hamda uning quyi
tashkilotlaridagi Axborot texnologiyalari xizmatlari faoliyatini yagona
markazlashgan tizimda boshqaruvchi, ishlab chiqarishga tayyor
(production-ready) web-platformani yaratish.

Loyihani faqat maket yoki demo sifatida emas, balki real foydalanish
uchun mo'ljallangan, xavfsiz, kengaytiriladigan, audit qilinadigan va
mobil qurilmalarga mos tizim sifatida ishlab chiq. Barcha modullar
o'zaro bog'langan bo'lsin. Ma'lumotlar takrorlanmasin, yagona
ma'lumotlar modeli va umumiy ruxsatlar mexanizmidan foydalan.

------------------------------------------------------------------------

# 1. Loyiha maqsadi va tashkiliy tuzilma

Tizim quyidagi tashkiliy modelni qo'llab-quvvatlashi kerak:

-   Markaziy apparat --- boshqaruvchi tashkilot.
-   Markaziy apparatga qarashli quyi tashkilotlar, MGQB, GTS va boshqa
    tarkibiy bo'linmalar.
-   Har bir tashkilot tarkibida bo'limlar/xizmatlar.
-   Har bir tashkilotning xodimlari.
-   Har bir tashkilotda AT bo'yicha mas'ul xodim(lar).
-   Markaziy AT xizmati --- barcha tashkilotlar bo'yicha nazorat,
    tasdiqlash, taqsimlash va hisobot yuritish vakolatiga ega.

Tashkilotlar soni va tarkibi kod ichida qotirib qo'yilmasin.
Administrator yangi tashkilot, bo'lim, xodim va AT mas'ulini tizimdan
qo'sha, tahrirlay va faoliyatini to'xtata olsin.

**Asosiy tamoyil:** quyi tashkilot foydalanuvchisi faqat o'z
tashkilotiga tegishli ma'lumotlarni ko'radi. Markaziy AT xizmati o'z
vakolati doirasida barcha quyi tashkilotlar ma'lumotlarini ko'radi va
boshqaradi.

# 2. Texnologik arxitektura

Quyidagi texnologiyalardan foydalan:

## Backend

-   PHP 8.3+.
-   Laravel 12 (yoki loyiha muhitiga mos eng barqaror
    qo'llab-quvvatlanadigan versiya).
-   RESTful API, API versioning: `/api/v1`.
-   Laravel Sanctum orqali autentifikatsiya.
-   Form Request validation, Policies/Gates va Service Layer.
-   Queue/Jobs va Scheduler --- fon vazifalari uchun.
-   Laravel Events/Listeners --- modullararo hodisalar uchun.
-   Fayllarni saqlash: Laravel Storage abstraction.
-   API hujjatlari: OpenAPI/Swagger.

## Database

-   MySQL 8.
-   Migratsiyalar, foreign key, indekslar va tranzaksiyalar to'g'ri
    tashkil etilsin.
-   Barcha jadvallarda zarur holatlarda `created_at`, `updated_at`,
    `created_by`, `updated_by`, `deleted_at` maydonlari bo'lsin.
-   Audit talab qilinadigan jadvallarda o'zgarishlar tarixi saqlansin.
-   Sana-vaqt bazada UTC yoki yagona kelishilgan formatda saqlanib,
    interfeysda `Asia/Tashkent` vaqt zonasida ko'rsatilsin.
-   Pul qiymatlari `DECIMAL` formatida saqlansin; `FLOAT` ishlatilmasin.

## Frontend

-   React.js, TypeScript.
-   Mantine UI --- asosiy komponentlar kutubxonasi.
-   React Router.
-   TanStack Query --- server state va API cache.
-   Zustand yoki Mantine hooks --- zarur bo'lgan client state.
-   React Hook Form + Zod --- formalar va validatsiya.
-   Axios yoki fetch asosidagi markazlashgan API client.
-   Grafiklar uchun Recharts yoki Mantine Charts.
-   Jadval, filtr, sort, pagination va eksport imkoniyatlari.

## Infratuzilma

-   Docker va Docker Compose.
-   Nginx reverse proxy.
-   Alohida frontend, backend, database, queue worker va scheduler
    servislar.
-   MySQL ma'lumotlari uchun persistent volume.
-   `.env` orqali konfiguratsiya.
-   Development, staging va production muhitlarini ajratish.
-   Healthcheck, log rotation va restart policy.
-   HTTPS Nginx orqali (TLS sertifikatni o'rnatish bo'yicha hujjat).
-   Production muhitida `APP_DEBUG=false`.

**Muhim:** foydalanuvchi bergan `root/root` qiymatlari faqat lokal
development uchun boshlang'ich misol hisoblanadi. Login/parolni kodga
yozma. `DB_USERNAME`, `DB_PASSWORD` va boshqa maxfiy qiymatlar `.env`
orqali berilsin. Production muhitida root hisobidan foydalanma; alohida
cheklangan huquqli DB foydalanuvchisi yaratilishi shart. `.env` Git'ga
qo'shilmasin.

# 3. Tizim tillari va interfeys

-   Dastlab o'zbek tili (lotin yozuvi) asosiy til bo'lsin.
-   Rus tili qo'shishga tayyor i18n arxitektura yarat.
-   Barcha interfeys matnlari tarjima fayllarida saqlansin.
-   Sana: `DD.MM.YYYY`, vaqt: `HH:mm`.
-   Pul birligi: UZS.
-   Interfeys desktop, tablet va mobile ekranlarga moslashsin.
-   Mobil brauzerda Helpdesk arizasi yaratish, topshiriqni ko'rish,
    tasdiqlash va bildirishnomalarni olish qulay bo'lsin.

# 4. Rollar va ruxsatlar (RBAC)

Rollarni kod ichida qotirib qo'yma. Super Admin rollar, ruxsatlar va
foydalanuvchilarni boshqara olsin. Laravel Policies va permission-based
authorization ishlat.

Boshlang'ich rollar:

1.  **Super Admin** --- tizimning barcha sozlamalari va barcha
    ma'lumotlari.
2.  **Markaziy AT rahbari** --- barcha tashkilotlar, arizalar, aktivlar,
    xarajatlar, topshiriqlar va hisobotlar.
3.  **Markaziy AT xodimi** --- biriktirilgan modullar va vazifalar
    doirasida ishlaydi.
4.  **Quyi tashkilot AT mas'uli** --- o'z tashkiloti xodimlari,
    aktivlari, Helpdesk arizalari, SIM-kartalari va lokal inventarini
    yuritadi.
5.  **Quyi tashkilot rahbari** --- o'z tashkiloti arizalarini ko'radi va
    belgilangan jarayonlarda tasdiqlaydi.
6.  **Oddiy xodim** --- Helpdesk arizasi yuboradi, o'z arizalari va
    o'ziga biriktirilgan aktivlarni ko'radi.
7.  **Buxgalter / moddiy javobgar shaxs** --- tegishli kirim-chiqim,
    hisobdan chiqarish va moliyaviy ma'lumotlar.
8.  **Auditor / kuzatuvchi** --- faqat o'qish va hisobot olish huquqi.

Har bir ruxsat modul, amal va tashkilot doirasida tekshirilsin.
Frontendda menyuni yashirishning o'zi xavfsizlik chorasi emas ---
backend har bir so'rovni tekshirishi shart. Tashkilotlararo ma'lumot
sizib chiqishiga yo'l qo'yma.

# 5. Asosiy modullar

## 5.1. Boshqaruv paneli (Dashboard)

Rolga qarab mos dashboard yarat.

Markaziy AT xizmati uchun: - Jami tashkilotlar va faol AT mas'ullari. -
Ochiq, jarayondagi, kechikkan va yopilgan Helpdesk arizalari. -
Tashkilotlar kesimida arizalar statistikasi. - Jami kompyuter, server,
printer, tarmoq qurilmalari va boshqa aktivlar. - Ishlayotgan, nosoz,
ta'mirda va hisobdan chiqarishga tayyor aktivlar. - Antivirus
o'rnatilgan/o'rnatilmagan qurilmalar. - Monitoring holati:
online/offline/unknown. - SIM-kartalar operatorlar va tashkilotlar
kesimida. - Internet va aloqa to'lovlari, muddati yaqinlashayotgan
to'lovlar. - Ombordagi materiallar va minimal qoldiq
ogohlantirishlari. - AT xodimlari topshiriqlari: bajarilgan,
bajarilmoqda, kechikkan. - Oxirgi muhim hodisalar va audit yozuvlari.

Quyi tashkilot dashboardida faqat o'z tashkilotiga tegishli
ko'rsatkichlar ko'rsatilsin.

## 5.2. Helpdesk / xizmat ko'rsatish arizalari

Xodimlar va quyi tashkilotlar AT xizmatiga ariza yubora olsin.

Ariza maydonlari: - Ariza raqami --- avtomatik, takrorlanmas. -
Tashkilot, bo'lim, murojaatchi. - Aloqa telefoni va email (mavjud
bo'lsa). - Kategoriya: kompyuter, printer, internet/tarmoq, telefon,
dasturiy ta'minot, hisob/foydalanuvchi, server, axborot xavfsizligi,
boshqa. - Muammo sarlavhasi va batafsil tavsif. - Ustuvorlik: past,
normal, yuqori, kritik. - Fayl/rasm ilovalari. - Biriktirilgan mas'ul AT
xodimi. - Holat: yangi, ko'rib chiqilmoqda, mas'ul tayinlangan,
jarayonda, foydalanuvchi javobi kutilmoqda, hal qilindi, yopildi, rad
etildi. - SLA muddati va kechikish belgisi. - Ishga sarflangan vaqt. -
Yechim tavsifi.

Funksiyalar: - Ariza yuborish, tahrirlash (holatga qarab), izoh
yozish. - Arizani mas'ul xodimga biriktirish va qayta biriktirish. -
Ichki izoh va murojaatchiga ko'rinadigan izohni farqlash. - Fayllar
biriktirish va yuklab olish huquqini tekshirish. - Arizalar tarixini
saqlash. - SLA va ustuvorlik asosida rangli indikatorlar. - Takroriy
muammolarni bog'lash. - Arizani qayta ochish va baholash. - Tashkilot,
kategoriya, holat, sana, mas'ul bo'yicha qidirish va filtrlash. -
Excel/PDF hisobot. - Email yoki tizim ichidagi bildirishnomalar;
keyinchalik Telegram integratsiyasiga tayyor arxitektura.

## 5.3. Tashkilotlar, bo'limlar va xodimlar

-   Tashkilotlar daraxti.
-   Tashkilot rekvizitlari va aloqa ma'lumotlari.
-   Bo'lim va xizmatlar.
-   Xodim F.I.Sh., lavozimi, bo'limi, telefon, email, tabel/ichki
    identifikator.
-   Ish holati: faol, ta'tilda, ishdan bo'shagan va boshqalar.
-   Tizim foydalanuvchisini xodim kartasiga bog'lash.
-   AT mas'ul xodimlarni tashkilotga biriktirish.
-   Xodimga biriktirilgan kompyuter, telefon, SIM va boshqa aktivlar.
-   Xodim ishdan ketganda aktivlarni qaytarish va foydalanuvchi
    huquqlarini bekor qilish jarayoni.

## 5.4. IT aktivlar va qurilmalar reyestri (CMDB)

Barcha IT aktivlarni yagona reyestrda yurit.

Aktiv toifalari: - Server va server infratuzilmasi. - Desktop kompyuter,
monoblok, noutbuk. - Monitor va docking station. - Printer, MFP,
skaner. - Router, switch, firewall, access point. - IP-telefon, telefon
stansiyasi. - UPS va elektr ta'minoti qurilmalari. - Proyektor,
videokonferensiya uskunalari. - Kabel, SFP, patch-panel va boshqa tarmoq
materiallari. - Boshqa ofis va IT texnikalari.

Aktiv kartasi: - Inventar raqami (unikal). - Tashkilot va bo'lim. -
Aktiv turi, ishlab chiqaruvchi, model. - Serial raqam, MAC manzil,
hostname. - IP manzil(lar), VLAN/subnet (mavjud bo'lsa). - Xarid sanasi,
kafolat muddati, qiymati. - Yetkazib beruvchi va shartnoma/hujjat. -
Moddiy javobgar shaxs. - Foydalanish joyi/xona. - Holati: omborda,
foydalanishda, ta'mirda, zaxirada, hisobdan chiqarilgan, yo'qolgan. -
Biriktirilgan xodim. - Texnik xususiyatlar va izoh. - Foto, dalolatnoma,
hisob-faktura va boshqa fayllar. - Harakatlar tarixi.

Talablar: - QR/barcode orqali aktivni tez topish. - Aktivni xodimga yoki
tashkilotga biriktirish/qaytarish. - Tashkilotlar o'rtasida ko'chirish
--- topshirish-qabul qilish dalolatnomasi bilan. - Inventarizatsiya
sessiyalari, faktik va hisobdagi holatni solishtirish. - Ta'mir tarixi
va kafolat ogohlantirishlari. - Aktivni hisobdan chiqarish jarayoni va
hujjatlari. - Aktivlar bo'yicha Excel/PDF eksport.

CMDB qurilma, IP, hostname va tarmoq bog'liqliklarini ko'rsatishga
tayyor bo'lsin.

## 5.5. Dasturiy ta'minot va antivirus nazorati

-   Dasturlar katalogi: nomi, ishlab chiqaruvchi, versiya, litsenziya
    turi.
-   Antivirus/EDR mahsuloti va litsenziya ma'lumotlari.
-   Kompyuterga o'rnatilishi kerak bo'lgan dasturlar siyosati.
-   Qurilma kesimida o'rnatilgan dasturlar ro'yxati.
-   Antivirus holati: o'rnatilgan, yangilangan, eskirgan, o'rnatilmagan,
    noma'lum.
-   Oxirgi inventarizatsiya va oxirgi yangilanish vaqti.
-   Litsenziyalar soni, foydalanilgan va bo'sh o'rinlar.
-   Litsenziya tugash sanasi va ogohlantirishlar.
-   Tashkilotlar kesimida muvofiqlik hisoboti.

**Muhim integratsiya talabi:** oddiy web-brauzer orqali kompyuterda
o'rnatilgan dasturlarni ishonchli aniqlab bo'lmaydi. Shu sababli keyingi
bosqichda Windows agent, Active Directory/GPO, antivirus konsoli yoki
boshqa inventarizatsiya vositalari bilan API orqali integratsiya qilish
uchun `device_agents`, `agent_tokens`, `software_inventory`,
`agent_checkins` kabi kengaytiriladigan model yarat. Agent tokenlari
maxfiy saqlansin, qurilma faqat o'ziga tegishli ma'lumotni yubora olsin.
Dastlab qo'lda kiritish va CSV importni ham qo'llab-quvvatla.

## 5.6. Tarmoq monitoringi (ICMP)

-   Monitoring obyektlari: server, router, switch, printer, firewall,
    access point va boshqa IP qurilmalar.
-   IP manzil, hostname, tashkilot, joylashuv, qurilma turi.
-   ICMP ping orqali mavjudlikni tekshirish.
-   Interval va timeout sozlamalari.
-   Holatlar: online, offline, unknown.
-   Oxirgi tekshiruv vaqti, oxirgi muvaffaqiyatli javob.
-   Uptime/availability statistikasi.
-   Javob vaqti (latency) grafigi.
-   Offline bo'lganda hodisa yaratish va tiklanganda hodisani yopish.
-   Bir qurilma bo'yicha takroriy ogohlantirishlarni birlashtirish
    (alert deduplication).
-   Tashkilot va qurilma turi bo'yicha filtr.
-   Monitoring tarixi va hisobot.

Texnik talablar: - Ping tekshiruvlari Laravel queue yoki alohida
monitoring worker orqali bajarilsin; HTTP request ichida ping
bajarilmasin. - Qurilmalar soni ko'p bo'lsa, parallel tekshiruvlar va
rate limit bo'lsin. - Faqat administrator ruxsat bergan IP/subnetlar
monitoring qilinsin. - Foydalanuvchi kiritgan manzilga cheklanmagan ping
yoki shell command bajarilishiga yo'l qo'yma. IP/CIDR allowlist, input
validation va xavfsiz process execution ishlat. - Monitoring
agenti/worker backenddan alohida Docker servis bo'lishi mumkin. - ICMP
huquqlari va Docker capabilities minimal darajada berilsin.

## 5.7. Mobil aloqa SIM-kartalari reyestri

Mobil operatorlar kesimida SIM-kartalar hisobini yurit: - Operator:
Uzmobile, Beeline, Ucell, Mobiuz va administrator qo'shadigan boshqa
operatorlar. - Telefon raqami. - SIM/ICCID raqami (maxfiylik va ruxsat
nazorati bilan). - Tarif rejasi. - Abonent/shartnoma raqami. - Oylik
abonent to'lovi. - Biriktirilgan tashkilot, bo'lim va xodim. - SIM
holati: omborda, faol, vaqtincha bloklangan, bekor qilingan,
qaytarilgan. - Ajratilgan sana, qaytarilgan sana. - SIM almashtirish va
tarix. - Operator bilan shartnoma va ilova hujjatlari.

Jarayon: - Markaziy AT xizmati SIM-kartalarni ro'yxatga oladi. - Quyi
tashkilotga SIM ajratish uchun elektron talabnoma yuboriladi. - Markaziy
AT ko'rib chiqadi, tasdiqlaydi yoki rad etadi. - Ajratilgan SIM quyi
tashkilot balansida/mas'ulida ko'rinadi. - Xodimga biriktirish va
qaytarish tarixi yuritiladi. - Operator va tashkilotlar kesimida
hisobot.

## 5.8. Internet, aloqa va AT xizmatlari to'lovlari

-   Internet provayderlari va aloqa operatorlari reyestri.
-   Shartnoma, xizmat turi, tashkilot, manzil.
-   Xizmat identifikatori, kanal tezligi yoki tarif.
-   Oylik to'lov, qo'shimcha xarajatlar.
-   Hisob-faktura va to'lov hujjatlari.
-   Hisob davri, to'lov muddati, to'langan sana.
-   To'lov holati: rejalashtirilgan, hisob-faktura kutilmoqda,
    to'lanishi kerak, qisman to'langan, to'langan, muddati o'tgan.
-   Markaziy AT xizmatining nazorati va tasdiqlash jarayoni.
-   Oylik/yillik xarajatlar tahlili.
-   Tashkilot, operator va xizmat turi kesimida hisobot.
-   To'lov muddati yaqinlashganda bildirishnoma.

Bu modul buxgalteriya tizimini almashtirmaydi; AT xizmatlari bo'yicha
operatsion nazorat va rejalashtirish vositasi sifatida ishlaydi.

## 5.9. Ombor, materiallar va kirim-chiqim

AT xizmati tasarrufidagi asosiy vositalar va materiallar hisobini yurit.

Materiallar: - Printer kartrijlari va tonerlar. - Qog'oz va ofis sarf
materiallari. - Klaviatura, sichqoncha, kabel, ulagich. - Patch cord,
SFP, adapter, akkumulyator. - Printer ehtiyot qismlari. - Boshqa IT
materiallari.

Funksiyalar: - Material katalogi, o'lchov birligi, kategoriya. - Ombor
va saqlash joylari. - Kirim: sana, miqdor, narx, yetkazib beruvchi,
hujjat. - Chiqim: kimga, qaysi tashkilotga, qaysi qurilmaga, miqdor va
sabab. - Qaytarish, omborlararo ko'chirish. - Qoldiq va minimal
qoldiq. - Inventarizatsiya. - Partiya/seriya raqami va yaroqlilik
muddati --- zarur toifalarda. - Har bir operatsiya bo'yicha audit izi. -
Ombor qoldig'i manfiy bo'lib ketmasin; parallel operatsiyalarda DB
transaction va locking ishlat. - FIFO usuli yoki sozlanadigan baholash
usuli uchun kengaytirishga tayyor model.

## 5.10. Quyi tashkilotlar talabnomalari va markaziy tasdiqlash

Quyi tashkilotlar quyidagi resurslar uchun talabnoma yubora olsin: -
Kompyuter, printer, monitor va boshqa texnika. - Tarmoq qurilmalari va
ehtiyot qismlar. - Kartrij, toner va boshqa sarf materiallari. -
SIM-karta va mobil aloqa xizmati. - Internet yoki aloqa xizmatlari. -
Dasturiy ta'minot va litsenziya. - Ta'mirlash yoki texnik xizmat. -
Boshqa AT ehtiyoji.

Workflow: 1. Quyi tashkilot AT mas'uli talabnoma yaratadi. 2. Zarur
bo'lsa, quyi tashkilot rahbari tasdiqlaydi. 3. Talabnoma markaziy AT
xizmatiga keladi. 4. Markaziy AT xodimi ko'rib chiqadi, izoh beradi va
zarur bo'lsa qo'shimcha ma'lumot so'raydi. 5. Markaziy AT rahbari
tasdiqlaydi/rad etadi yoki qayta ishlashga yuboradi. 6. Ombor yoki
mas'ul xodim resursni ajratadi. 7. Qabul qiluvchi elektron tarzda qabul
qilinganini tasdiqlaydi. 8. Ajratilgan resurs tegishli aktiv yoki
material hisobiga avtomatik bog'lanadi. 9. Barcha bosqichlar, izohlar va
hujjatlar tarixda saqlanadi.

Talabnoma holatlari: draft, yuborilgan, tashkilot rahbari tasdig'ida,
markaziy ko'rib chiqishda, ma'lumot kutilmoqda, tasdiqlangan, rad
etilgan, ajratishga tayyor, qisman ajratilgan, to'liq ajratilgan, qabul
qilingan, yopilgan.

## 5.11. AT xodimlari topshiriqlari --- Kanban

-   Board, column va task modeli.
-   Standart ustunlar: Backlog, Rejalashtirilgan, Jarayonda,
    Tekshiruvda, Bajarildi, Bekor qilindi.
-   Ustunlar administrator tomonidan sozlanishi mumkin.
-   Topshiriq nomi, tavsifi, tashkilot, loyiha/yo'nalish.
-   Mas'ul xodim va kuzatuvchilar.
-   Ustuvorlik, boshlanish va tugash muddati.
-   Checklist/subtask.
-   Izohlar va fayllar.
-   Yorliqlar (labels).
-   Kanban drag-and-drop.
-   Kechikkan topshiriqlar indikatori.
-   Takrorlanuvchi vazifalar.
-   Topshiriq o'zgarishlari tarixi.
-   Markaziy AT rahbari xodimlar va tashkilotlar kesimida yuklamani
    ko'ra olsin.
-   Helpdesk arizasidan topshiriq yaratish imkoniyati.

## 5.12. Printerlar va sarf materiallari nazorati

-   Printer reyestri va unga biriktirilgan tashkilot/xona/xodim.
-   Printer modeli, serial, IP, ulanish turi.
-   Mos kartrij/toner modeli.
-   Kartrij almashtirish tarixi.
-   Kartrij qoldig'i, kirimi va chiqimi.
-   Qaysi printerga qaysi kartrij berilgani.
-   Printer hisoblagichi (agar qo'lda yoki integratsiya orqali
    olinadigan bo'lsa).
-   Sahifa bosish statistikasi (mavjud ma'lumot asosida).
-   Nosozlik va ta'mir tarixi.
-   Kartrij sarfi bo'yicha tashkilotlar kesimida tahlil.
-   Sarf materiallari ombor moduli bilan integratsiya.

## 5.13. Hujjatlar va elektron reyestr

-   Shartnoma, dalolatnoma, talabnoma, hisob-faktura, texnik pasport,
    kafolat hujjati va boshqa fayllar.
-   Hujjat turi, raqami, sanasi, tashkilot va tegishli obyektga
    bog'lash.
-   Fayl versiyalari va yuklagan foydalanuvchi.
-   Faylga kirish huquqi tegishli obyekt ruxsatlari bilan bir xil
    bo'lsin.
-   Fayllarni xavfsiz saqlash; ochiq public URL orqali maxfiy
    hujjatlarni tarqatma.
-   Fayl turi va hajmini cheklash, MIME tekshiruvi, xavfli fayllarni
    bloklash.

## 5.14. Bildirishnomalar va eslatmalar

-   Tizim ichidagi bildirishnomalar.
-   Yangi ariza, tayinlangan mas'ul, izoh, tasdiqlash, rad etish,
    muddati yaqinlashgan topshiriq.
-   Offline qurilma va monitoring hodisalari.
-   Litsenziya yoki kafolat muddati tugashi.
-   SIM/aloqa to'lovi muddati.
-   Ombor minimal qoldig'i.
-   Foydalanuvchi bildirishnoma sozlamalari.
-   Keyinchalik email, Telegram yoki SMS integratsiyasi uchun
    Notification channel arxitekturasi.

## 5.15. Hisobotlar va analitika

Barcha hisobotlarda tashkilot, sana oralig'i, kategoriya va holat
bo'yicha filtr bo'lsin.

Hisobotlar: - Helpdesk SLA va bajarilish ko'rsatkichlari. - Tashkilotlar
kesimida murojaatlar. - IT aktivlar balansi va holati. - Xodimlarga
biriktirilgan aktivlar. - Antivirus/dasturiy ta'minot muvofiqligi. -
Tarmoq qurilmalari online/offline statistikasi. - SIM-kartalar operator
va tashkilot kesimida. - Internet/aloqa xarajatlari. - Materiallar
kirim-chiqimi va qoldig'i. - Kartrij va printer sarfi. - Talabnomalar
ijrosi. - AT xodimlari topshiriqlari va yuklamasi. - Audit va
foydalanuvchi harakatlari.

Excel va PDF eksport. Katta hisobotlar queue orqali generatsiya
qilinsin.

# 6. Qo'shimcha zarur modullar

Quyidagilarni ham arxitekturada hisobga ol: - Bilimlar bazasi (Knowledge
Base): FAQ, yo'riqnoma, yechimlar. - O'zgarishlarni boshqarish (Change
Management): tarmoq, server va muhim konfiguratsiya o'zgarishlari. -
Muammo boshqaruvi (Problem Management): takrorlanuvchi muammolar va
root-cause. - Rejali texnik xizmat (Preventive Maintenance): server,
UPS, printer va tarmoq qurilmalariga xizmat jadvali. - Shartnoma va
kafolatlar reyestri. - Litsenziyalar reyestri. - IT xavfsizlik
hodisalari reyestri. - Zaxira nusxa (backup) holati va tekshiruv
jurnali. - Tashkilotlar bo'yicha AT pasporti. - Tizim sozlamalari va
ma'lumotnomalar. - Audit log va faoliyat tarixi.

# 7. UI/UX dizayn talablari

Zamonaviy enterprise SaaS darajasidagi dizayn yarat.

Dizayn yo'nalishi: - Toza, professional, minimal va o'qilishi oson
interfeys. - Mantine UI komponentlari asosida yagona design system. -
Light theme asosiy, dark theme qo'llab-quvvatlansin. - Chap sidebar:
modullar va bo'limlar; yig'iladigan (collapsible). - Yuqori panel:
breadcrumb, global qidiruv, bildirishnomalar, profil va tashkilot
konteksti. - Dashboardda ixcham statistik kartalar, grafiklar va so'nggi
hodisalar. - Jadvalda server-side pagination, filter, sorting, column
visibility. - Har bir jadvalda bo'sh holat (empty state), loading
skeleton, error state va qayta urinish. - Katta formalarni tab/stepper
yoki mantiqiy bo'limlarga ajrat. - Tasdiqlash/rad etish amallarida aniq
confirmation dialog. - Statuslar uchun izchil rang va badge tizimi. -
Toast notification. - Accessibility: klaviatura navigatsiyasi, kontrast,
label va aria atributlari. - Mobil ekranda sidebar drawer, moslashuvchan
jadvallar va qulay formalar. - Keraksiz animatsiya va ortiqcha
bezaklardan qoch.

Interfeysda real ma'lumot yo'q paytda mazmunli empty state ko'rsat. Demo
ma'lumotlarni production seed bilan aralashtirma.

# 8. Ma'lumotlar bazasi va domen modeli

Modullararo bog'liqliklarni oldindan loyihala. Kamida quyidagi asosiy
entity/jadvallarni ko'rib chiq:

-   organizations
-   departments
-   employees
-   users
-   roles, permissions, model_has_roles, model_has_permissions
    (tanlangan RBAC paketiga mos)
-   user_organization_access
-   helpdesk_tickets, ticket_categories, ticket_comments,
    ticket_attachments, ticket_histories, ticket_sla_policies
-   assets, asset_categories, asset_assignments, asset_transfers,
    asset_maintenance, asset_disposals, asset_documents
-   network_devices, monitoring_checks, monitoring_events,
    monitoring_policies
-   software_catalog, software_licenses, software_installations,
    device_agents, agent_tokens, agent_checkins
-   mobile_operators, sim_cards, sim_assignments, sim_histories
-   service_providers, service_contracts, service_services,
    service_invoices, service_payments
-   warehouses, inventory_items, stock_receipts, stock_issues,
    stock_transfers, stock_balances, stock_movements
-   requisitions, requisition_items, requisition_approvals,
    requisition_histories, requisition_fulfillments
-   task_boards, task_columns, tasks, task_assignees, task_comments,
    task_checklists, task_labels
-   printers, printer_models, cartridge_models,
    printer_cartridge_compatibilities, cartridge_movements
-   documents, document_links
-   notifications
-   audit_logs
-   system_settings

Bu ro'yxat boshlang'ich yo'nalish. Takroriy ma'lumotlar va keraksiz
murakkablik yaratmasdan, normalizatsiyalangan va kelajakda
kengaytiriladigan sxema ishlab chiq. Kerakli indekslar, unique
constraints, foreign key va cascade strategiyasini hujjatlashtir. Muhim
biznes operatsiyalarini DB transaction ichida bajar.

# 9. API va backend standartlari

-   Barcha endpointlar `/api/v1` ostida.
-   REST naming convention.
-   Yagona JSON response formati.
-   Pagination, filtering, sorting va qidiruv standartlashtirilsin.
-   Validatsiya Form Request orqali.
-   Business logic Controller ichida yig'ilmasin; Service/Action
    classlarga ajratilsin.
-   Policy orqali obyekt darajasidagi ruxsatlar tekshirilsin.
-   API rate limiting.
-   Login urinishlari va parol tiklash himoyasi.
-   Fayl yuklashda MIME, hajm va ruxsat tekshiruvi.
-   Idempotency talab qilinadigan operatsiyalarni himoyalash.
-   API hujjatlari va misol so'rov/javoblar.
-   Testlar: Unit, Feature va API authorization testlari.

# 10. Xavfsizlik talablari

-   Parollarni Laravel password hashing bilan saqlash.
-   MFA/2FA uchun keyingi bosqichga tayyor arxitektura.
-   CSRF, XSS, SQL injection, mass assignment va IDOR himoyasi.
-   Backendda tashkilot scope majburiy tekshirilsin.
-   Secret va tokenlar logga yozilmasin.
-   Audit logni oddiy foydalanuvchi o'zgartira olmasin.
-   Muhim amallarda kim, qachon, nima o'zgartirgani saqlansin.
-   Sessiyalar va tokenlar xavfsiz boshqarilsin.
-   Fayllar private storage'da saqlansin.
-   Backup siyosati: DB va fayllar uchun reja, retention va restore
    sinovi.
-   Production uchun HTTPS.
-   Xatoliklarda maxfiy texnik tafsilotlarni foydalanuvchiga ko'rsatma.
-   Loglarda shaxsiy ma'lumotlarni minimallashtir.
-   Xavfsizlikka oid konfiguratsiyalar `.env` va secret management
    orqali.

# 11. Docker va Nginx infratuzilmasi

Quyidagi servislarni Docker Compose orqali tashkil et: - `nginx` -
`frontend` - `backend` (PHP-FPM) - `queue-worker` - `scheduler` -
`mysql` - Zarur bo'lsa `redis` (queue/cache uchun).

Talablar: - Multi-stage Dockerfile. - Production image ichida
development dependency bo'lmasin. - Nginx frontend va API routingni
to'g'ri bajarsin. - `/api` backendga, qolgan route'lar React SPA'ga
yo'naltirilsin. - Laravel storage va bootstrap cache permissionlari
to'g'ri sozlansin. - DB portini productionda internetga ochma. -
Healthcheck va depends_on health condition. - Persistent volume va
backup/restore yo'riqnomasi. - `.dockerignore`, `.gitignore`,
`.env.example`. - `docker compose up -d --build` bilan ishga tushirish
hujjati. - Migration va seed buyruqlari. - Loglarni ko'rish va
servislarni yangilash yo'riqnomasi.

# 12. Test va sifat talablari

-   Har bir modul uchun asosiy CRUD va biznes jarayon testlari.
-   Tashkilotlararo ma'lumotga ruxsatsiz kirish testlari.
-   Rol va permission testlari.
-   Ombor qoldig'i, talabnoma tasdiqlash va aktiv ko'chirish uchun
    transaction testlari.
-   Monitoring worker testlari (ping natijasini mock qilish).
-   Frontend asosiy sahifalari va muhim formalar testlari.
-   Lint, formatter va TypeScript strict mode.
-   Kodda `TODO` bilan tugallanmagan asosiy funksiyalar qoldirma.
-   Xatoliklarni markazlashgan tarzda qayd et.

# 13. Ishni bajarish tartibi

Loyihani birdaniga barcha modullarni yozib tashlash orqali emas,
bosqichma-bosqich, ishlaydigan holatda yarat.

## 0-bosqich --- mavjud loyihani tahlil qilish

1.  Repository tarkibini tekshir.
2.  Mavjud kod va konfiguratsiyalarni buzma.
3.  Ishlatilayotgan versiyalar va muammolarni aniqlab, qisqa tahlil yoz.
4.  `README.md`, `docs/ARCHITECTURE.md`, `docs/ROADMAP.md`,
    `docs/DATABASE.md`, `docs/API.md` hujjatlarini yarat.

## 1-bosqich --- Foundation

-   Laravel va React/TypeScript/Mantine loyihasini sozlash.
-   Docker Compose va Nginx.
-   Auth, login/logout, password reset.
-   Organization, department, employee.
-   RBAC va tashkilot scope.
-   Audit log.
-   Umumiy layout, sidebar, header, breadcrumbs, responsive design.
-   Dashboard skeleton va sozlamalar.

## 2-bosqich --- Helpdesk

-   Kategoriya, ariza, izoh, fayl, status workflow.
-   Mas'ul tayinlash.
-   SLA va bildirishnomalar.
-   Tashkilotlar kesimida cheklovlar.
-   Dashboard va hisobotlar.

## 3-bosqich --- IT aktivlar va CMDB

-   Aktiv katalogi va kartasi.
-   Xodimga biriktirish/qaytarish.
-   Tashkilotlararo ko'chirish.
-   Inventarizatsiya.
-   Hujjatlar va QR/barcode.

## 4-bosqich --- Ombor va talabnomalar

-   Material katalogi.
-   Kirim-chiqim va qoldiq.
-   Quyi tashkilot talabnomasi.
-   Markaziy tasdiqlash va resurs ajratish.
-   Qabul qilish va audit.

## 5-bosqich --- Monitoring va dasturiy ta'minot

-   ICMP monitoring worker.
-   Hodisalar va availability.
-   Qurilma inventarizatsiyasi.
-   Antivirus va dasturlar nazorati.
-   Agent/API integratsiyasiga tayyor model.

## 6-bosqich --- SIM, xizmatlar va to'lovlar

-   SIM-kartalar va operatorlar.
-   Internet/aloqa xizmatlari.
-   Shartnomalar, hisob-fakturalar va to'lov nazorati.

## 7-bosqich --- Kanban, printer va qolgan modullar

-   Kanban topshiriqlar.
-   Printer/kartrij hisobi.
-   Knowledge Base.
-   Change/Problem/Preventive Maintenance.
-   Kengaytirilgan hisobotlar.

Har bosqich yakunida: 1. Migratsiyalarni ishga tushir. 2. Testlarni
bajar. 3. Frontend build va lintni bajar. 4. Docker orqali ishga
tushirishni tekshir. 5. README va tegishli hujjatlarni yangila. 6.
Bajarilgan va qolgan ishlarni aniq ro'yxat qil.

# 14. Birinchi ishga tushirish va demo ma'lumotlar

-   `DatabaseSeeder` orqali faqat development uchun demo tashkilotlar,
    foydalanuvchilar va namunaviy ma'lumotlar yarat.
-   Super Admin hisobini `.env` orqali sozlanadigan qilib yarat yoki
    birinchi ishga tushirishda xavfsiz CLI command orqali tashkil et.
-   Standart production parolni kodga yozma.
-   Demo ma'lumotlar productionda avtomatik yaratilmasin.
-   Birinchi login paytida parolni almashtirish talabi bo'lsin.
-   Seed jarayoni takroran ishlatilganda dublikat yaratmasin.

# 15. Claude Code uchun ishlash qoidalari

1.  Avval mavjud repositoryni tekshir, keyin reja tuz.
2.  Foydalanuvchi tasdiqlamagan holda mavjud fayllarni ommaviy o'chirib
    yuborma yoki loyihani noldan qayta yaratma.
3.  Har bir modulni to'liq backend + frontend + migration + validation +
    authorization + test bilan yakunla.
4.  Faqat UI maketini yaratib, ishlamaydigan tugmalar qoldirma.
5.  Har bir tugma real API yoki aniq funksional amal bilan bog'langan
    bo'lsin.
6.  Ma'lumot yo'q bo'lsa, bo'sh jadval va tushunarli empty state
    ko'rsat.
7.  Mock API va hardcoded statistikani production kodida qoldirma.
8.  Takrorlanuvchi kodni reusable component, hook, service va traitlarga
    ajrat.
9.  Katta fayllar va haddan tashqari katta Controllerlardan qoch.
10. Kodni yozgandan so'ng test va buildni ishga tushir; xatolarni tuzat.
11. Har bir bosqichda bajarilgan ishlar, o'zgargan fayllar, ishga
    tushirish buyruqlari va keyingi vazifalarni qisqa hisobot qil.
12. Aniqlashtirish zarur bo'lgan biznes qoidalarini taxmin bilan
    yashirma; `docs/OPEN_QUESTIONS.md`ga yoz va xavfsiz standart yechim
    bilan davom et.
13. Barcha vaqt, valyuta, tarjima va tashkilot scope qoidalariga izchil
    amal qil.
14. Kod va hujjatlarda loyiha nomi **AT.urtg.uz --- Axborot
    texnologiyalari xizmatini boshqarish tizimi** sifatida yuritilsin.

# 16. Yakuniy qabul mezonlari

Tizim quyidagi shartlar bajarilgandagina tayyor deb hisoblanadi:

-   Docker orqali toza muhitda ishga tushadi.
-   Login va RBAC to'g'ri ishlaydi.
-   Quyi tashkilot boshqa tashkilot ma'lumotini ko'ra olmaydi.
-   Markaziy AT xizmati barcha tashkilotlarni boshqara oladi.
-   Helpdesk arizasi to'liq workflow bilan ishlaydi.
-   Aktivlar xodim va tashkilotlarga biriktiriladi, ko'chiriladi va
    inventarizatsiya qilinadi.
-   Ombor kirim-chiqimi va qoldig'i aniq yuritiladi.
-   Talabnomalar markaziy tasdiqlash va ajratish jarayonidan o'tadi.
-   ICMP monitoring fon jarayonida ishlaydi va hodisalarni qayd etadi.
-   SIM-kartalar, internet xizmatlari va to'lovlar nazorat qilinadi.
-   Kanban topshiriqlar real ishlaydi.
-   Printer va kartrij sarfi hisobga olinadi.
-   Audit, eksport, bildirishnomalar va hujjatlar ishlaydi.
-   Muhim backend va frontend testlari muvaffaqiyatli o'tadi.
-   README orqali yangi dasturchi tizimni o'rnata oladi.

------------------------------------------------------------------------

## Claude Code uchun boshlang'ich topshiriq

Hozir repositoryni tahlil qil. Mavjud loyiha bo'lsa, uning tuzilmasini
saqla va yuqoridagi talablarni unga moslashtir. Mavjud loyiha bo'lmasa,
arxitektura va papkalar tuzilmasini taklif qil.

Avval quyidagilarni bajar: 1. `docs/ARCHITECTURE.md` va
`docs/ROADMAP.md`ni yoz. 2. Backend/frontend papkalar tuzilmasini
belgilab ber. 3. Docker Compose va Nginx konfiguratsiyasini tayyorla. 4.
Database ERD va asosiy migratsiyalar rejasini hujjatlashtir. 5. RBAC va
tashkilotlararo ma'lumot ajratish strategiyasini ishlab chiq. 6. So'ng
1-bosqich Foundationni to'liq amalga oshir va test qil.

**Muhim:** birinchi bosqich tugagach, keyingi bosqichga o'tishdan oldin
bajarilgan ishlar va test natijalarini hisobot qil. Keyingi modullarni
ham shu tartibda, bosqichma-bosqich ishlab chiq. Yakuniy maqsad --- real
foydalanishga tayyor, xavfsiz va to'liq ishlaydigan `at.urtg.uz`
platformasi.
