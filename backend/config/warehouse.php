<?php

/*
|--------------------------------------------------------------------------
| Warehouse (Phase 4)
|--------------------------------------------------------------------------
| Units and material categories are seeded in every environment by
| WarehouseSeeder (firstOrCreate by code — admin edits are kept). Categories
| follow spec §5.9.
*/

return [

    // Lots expiring within this many days raise an "expiring" alert (once per lot and date).
    'expiry_notice_days' => (int) env('WAREHOUSE_EXPIRY_NOTICE_DAYS', 30),

    'units' => [
        ['code' => 'pcs', 'name' => 'dona', 'name_ru' => 'шт.'],
        ['code' => 'box', 'name' => 'quti', 'name_ru' => 'коробка'],
        ['code' => 'pack', 'name' => 'pachka', 'name_ru' => 'пачка'],
        ['code' => 'm', 'name' => 'metr', 'name_ru' => 'м'],
        ['code' => 'kg', 'name' => 'kg', 'name_ru' => 'кг'],
        ['code' => 'l', 'name' => 'litr', 'name_ru' => 'л'],
        ['code' => 'set', 'name' => 'komplekt', 'name_ru' => 'компл.'],
    ],

    'categories' => [
        ['code' => 'cartridge', 'name' => 'Printer kartrijlari va tonerlar', 'name_ru' => 'Картриджи и тонеры'],
        ['code' => 'paper', 'name' => 'Qog\'oz va ofis sarf materiallari', 'name_ru' => 'Бумага и офисные расходники'],
        ['code' => 'peripheral', 'name' => 'Klaviatura, sichqoncha, kabel, ulagich', 'name_ru' => 'Клавиатуры, мыши, кабели, разъёмы'],
        ['code' => 'network', 'name' => 'Patch cord, SFP, adapter, akkumulyator', 'name_ru' => 'Патч-корды, SFP, адаптеры, аккумуляторы'],
        ['code' => 'printer_part', 'name' => 'Printer ehtiyot qismlari', 'name_ru' => 'Запчасти для принтеров'],
        ['code' => 'other', 'name' => 'Boshqa IT materiallari', 'name_ru' => 'Прочие ИТ-материалы'],
    ],

];
