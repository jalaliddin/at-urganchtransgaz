<?php

/*
|--------------------------------------------------------------------------
| IT asset reference data (spec §5.4)
|--------------------------------------------------------------------------
| Seeded once by AssetsSeeder in every environment; admin edits survive.
| is_networked marks devices with an IP/hostname (monitoring in Phase 5).
*/

return [

    'categories' => [
        ['code' => 'server', 'name' => 'Server va server infratuzilmasi', 'name_ru' => 'Серверы и серверная инфраструктура', 'is_networked' => true],
        ['code' => 'computer', 'name' => 'Kompyuter, monoblok, noutbuk', 'name_ru' => 'Компьютер, моноблок, ноутбук', 'is_networked' => true],
        ['code' => 'monitor', 'name' => 'Monitor va docking station', 'name_ru' => 'Монитор и док-станция', 'is_networked' => false],
        ['code' => 'printer', 'name' => 'Printer, MFP, skaner', 'name_ru' => 'Принтер, МФУ, сканер', 'is_networked' => true],
        ['code' => 'network', 'name' => 'Router, switch, firewall, access point', 'name_ru' => 'Маршрутизатор, коммутатор, межсетевой экран, точка доступа', 'is_networked' => true],
        ['code' => 'telephony', 'name' => 'IP-telefon, telefon stansiyasi', 'name_ru' => 'IP-телефон, телефонная станция', 'is_networked' => true],
        ['code' => 'power', 'name' => 'UPS va elektr ta\'minoti', 'name_ru' => 'ИБП и электропитание', 'is_networked' => false],
        ['code' => 'av', 'name' => 'Proyektor, videokonferensiya', 'name_ru' => 'Проектор, видеоконференцсвязь', 'is_networked' => false],
        ['code' => 'network_material', 'name' => 'Kabel, SFP, patch-panel', 'name_ru' => 'Кабель, SFP, патч-панель', 'is_networked' => false],
        ['code' => 'other', 'name' => 'Boshqa ofis va IT texnikasi', 'name_ru' => 'Прочая офисная и ИТ-техника', 'is_networked' => false],
    ],

    // Days before warranty end when asset managers are notified.
    'warranty_notice_days' => 30,

];
