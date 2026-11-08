<?php

return [
    'open_ticket' => 'Arizani ochish',
    'ticket_created' => [
        'subject' => 'Yangi ariza :number',
        'line' => ':organization tashkilotidan yangi ariza keldi (ustuvorlik: :priority).',
    ],
    'ticket_assigned' => [
        'subject' => 'Sizga ariza biriktirildi: :number',
        'line' => ':actor sizni ushbu arizaga mas\'ul etib tayinladi.',
    ],
    'ticket_status_changed' => [
        'subject' => ':number arizasi holati: :status',
        'line' => 'Arizangiz holati o\'zgardi: :status.',
    ],
    'ticket_commented' => [
        'subject' => ':number arizasiga yangi izoh',
        'line' => ':actor arizaga izoh qoldirdi.',
    ],
    'ticket_sla_breached' => [
        'subject' => ':number arizasi muddati o\'tdi',
        'line' => 'Arizaning :kind muddati o\'tib ketdi.',
    ],
    'ticket_due_soon' => [
        'subject' => ':number arizasi muddati yaqinlashmoqda',
        'line' => 'Arizani hal qilish muddati: :due_at.',
    ],
    'sla_kind' => [
        'response' => 'javob berish',
        'resolution' => 'hal qilish',
    ],
    'open_exports' => 'Eksportlarni ochish',
    'export_ready' => [
        'subject' => ':report eksporti tayyor',
        'line' => ':report hisoboti yuklab olishga tayyor.',
    ],
    'open_employee' => 'Xodim kartasini ochish',
    'assets_return_required' => [
        'subject' => ":employee ishdan bo'shadi — aktivlarni qaytarib oling",
        'line' => ":employee ishdan bo'shadi, unda :count ta aktiv bor.",
    ],
    'open_transfer' => "Ko'chirishni ochish",
    'asset_transfer_incoming' => [
        'subject' => ":number: aktivlar sizning tashkilotingizga jo'natildi",
        'line' => ":from tashkilotidan aktivlar jo'natildi. Ularni tekshirib, qabul qiling.",
    ],
    'open_disposals' => "Hisobdan chiqarish so'rovlari",
    'open_assets' => 'Aktivlarni ochish',
    'asset_disposal_requested' => [
        'subject' => ":number aktivini hisobdan chiqarish so'raldi",
        'line' => ":number — :name aktivini hisobdan chiqarish so'rovi qarorni kutmoqda.",
    ],
    'asset_warranty_expiring' => [
        'subject' => ':organization: :count ta aktiv kafolati tugayapti',
        'line' => ':organization tashkilotida :count ta aktivning kafolati :days kun ichida tugaydi.',
    ],
    'stock_low' => [
        'subject' => ':warehouse: :material minimal qoldiqdan kam',
        'line' => ':warehouse omborida :material qoldig\'i :quantity :unit (minimal — :minimum :unit).',
    ],
    'stock_expiring' => [
        'subject' => ':count ta partiyaning yaroqlilik muddati tugayapti',
        'line' => ':count ta partiyaning yaroqlilik muddati :days kun ichida tugaydi:',
    ],
    'open_warehouse' => 'Omborni ochish',
];
