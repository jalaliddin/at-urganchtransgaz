<?php

/*
|--------------------------------------------------------------------------
| Helpdesk reference data
|--------------------------------------------------------------------------
| Seeded once by HelpdeskSeeder (every environment, idempotent; admin edits
| survive). Runtime calendar/SLA knobs live in config/settings.php.
| SLA minutes are business minutes (see BusinessCalendar).
*/

return [

    'categories' => [
        ['code' => 'computer', 'name' => 'Kompyuter', 'name_ru' => 'Компьютер'],
        ['code' => 'printer', 'name' => 'Printer', 'name_ru' => 'Принтер'],
        ['code' => 'network', 'name' => 'Internet / tarmoq', 'name_ru' => 'Интернет / сеть'],
        ['code' => 'phone', 'name' => 'Telefon', 'name_ru' => 'Телефон'],
        ['code' => 'software', 'name' => 'Dasturiy ta\'minot', 'name_ru' => 'Программное обеспечение'],
        ['code' => 'account', 'name' => 'Hisob / foydalanuvchi', 'name_ru' => 'Учётная запись / пользователь'],
        ['code' => 'server', 'name' => 'Server', 'name_ru' => 'Сервер'],
        ['code' => 'security', 'name' => 'Axborot xavfsizligi', 'name_ru' => 'Информационная безопасность'],
        ['code' => 'other', 'name' => 'Boshqa', 'name_ru' => 'Другое'],
    ],

    // priority => [response, resolution] in business minutes (8 h business day = 480).
    'default_sla' => [
        'critical' => [30, 240],
        'high' => [60, 480],
        'normal' => [120, 1440],
        'low' => [480, 2400],
    ],

];
