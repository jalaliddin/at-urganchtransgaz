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
    'requisition' => [
        'org_approval' => [
            'subject' => ':number talabnomasi tasdiqlashingizni kutmoqda',
            'line' => ":organization: «:title» talabnomasi tashkilot rahbari tasdig'ini kutmoqda.",
        ],
        'queued' => [
            'subject' => ':number talabnomasi markaziy AT xizmatiga keldi',
            'line' => ":organization: «:title» talabnomasi ko'rib chiqishni kutmoqda.",
        ],
        'info_requested' => [
            'subject' => ":number: qo'shimcha ma'lumot so'raldi",
            'line' => "«:title» talabnomasi bo'yicha markaziy AT xizmati qo'shimcha ma'lumot so'radi.",
        ],
        'info_provided' => [
            'subject' => ":number: ma'lumot berildi",
            'line' => "«:title» talabnomasi bo'yicha so'ralgan ma'lumot berildi.",
        ],
        'approved' => [
            'subject' => ':number talabnomasi tasdiqlandi',
            'line' => '«:title» talabnomasi markaziy AT rahbari tomonidan tasdiqlandi.',
        ],
        'allocation' => [
            'subject' => ':number: resurs ajratish kerak',
            'line' => ':organization: «:title» talabnomasi tasdiqlandi, resurslarni ajrating.',
        ],
        'rejected' => [
            'subject' => ':number talabnomasi rad etildi',
            'line' => '«:title» talabnomasi rad etildi.',
        ],
        'returned' => [
            'subject' => ':number talabnomasi qayta ishlashga qaytarildi',
            'line' => "«:title» talabnomasini to'g'rilab, qayta yuboring.",
        ],
        'partially_fulfilled' => [
            'subject' => ':number: resurslar qisman ajratildi',
            'line' => "«:title» talabnomasi bo'yicha resurslarning bir qismi ajratildi.",
        ],
        'fulfilled' => [
            'subject' => ':number: resurslar ajratildi — qabul qiling',
            'line' => "«:title» talabnomasi bo'yicha resurslar to'liq ajratildi. Qabul qilinganini tasdiqlang.",
        ],
        'received' => [
            'subject' => ':number: resurslar qabul qilindi',
            'line' => ":organization: «:title» talabnomasi bo'yicha resurslar qabul qilinganini tasdiqladi.",
        ],
    ],
    'open_requisition' => 'Talabnomani ochish',
    'license_expiring' => [
        'subject' => ':software litsenziyasi tugayapti (:date)',
        'line' => ':organization: :software litsenziyasi (:number) :date da tugaydi.',
    ],
    'open_licenses' => 'Litsenziyalarni ochish',
    'service_payment' => [
        'due_soon' => [
            'subject' => "To'lov muddati yaqin: :service (:date)",
            'line' => ":organization: :provider — :service bo'yicha :amount so'm :date gacha to'lanishi kerak.",
        ],
        'overdue' => [
            'subject' => "To'lov muddati o'tdi: :service (:date)",
            'line' => ":organization: :provider — :service bo'yicha :amount so'm :date da to'lanishi kerak edi.",
        ],
    ],
    'open_invoice' => 'Hisob-fakturani ochish',
    'contract_expiring' => [
        'subject' => ':number shartnomasi tugayapti (:date)',
        'line' => ':organization: :provider bilan :number shartnomasi :date da tugaydi.',
    ],
    'open_contract' => 'Shartnomani ochish',
    'task' => [
        'assigned' => [
            'subject' => ":number topshirig'i sizga biriktirildi",
            'line' => ":actor sizga «:title» topshirig'ini biriktirdi.",
        ],
        'commented' => [
            'subject' => ":number topshirig'iga izoh",
            'line' => ":actor «:title» topshirig'iga izoh qoldirdi.",
        ],
        'due_soon' => [
            'subject' => ":number topshirig'i muddati ertaga (:date)",
            'line' => "«:title» topshirig'ining muddati :date.",
        ],
        'overdue' => [
            'subject' => ":number topshirig'i muddati o'tdi (:date)",
            'line' => "«:title» topshirig'i :date gacha bajarilishi kerak edi.",
        ],
    ],
    'open_task' => 'Topshiriqni ochish',
    'problem' => [
        'assigned' => [
            'subject' => ':number muammosi sizga biriktirildi',
            'line' => ':actor sizga «:title» muammosini biriktirdi.',
        ],
        'resolved' => [
            'subject' => ':number muammosi hal qilindi',
            'line' => '«:title» muammosi hal qilindi. Unga bog\'langan arizangizni yopish mumkin.',
        ],
    ],
    'open_problem' => 'Muammoni ochish',
    'change' => [
        'approval' => [
            'subject' => ':number o\'zgarishi tasdiqni kutmoqda',
            'line' => ':actor «:title» o\'zgarishini tasdiqqa yubordi (:date).',
        ],
        'emergency' => [
            'subject' => 'Shoshilinch o\'zgarish :number',
            'line' => ':actor «:title» shoshilinch o\'zgarishini ro\'yxatga oldi (:date).',
        ],
        'approved' => [
            'subject' => ':number o\'zgarishi tasdiqlandi',
            'line' => ':actor «:title» o\'zgarishini tasdiqladi.',
        ],
        'rejected' => [
            'subject' => ':number o\'zgarishi rad etildi',
            'line' => ':actor «:title» o\'zgarishini rad etdi.',
        ],
        'assigned' => [
            'subject' => ':number o\'zgarishini bajarish sizga topshirildi',
            'line' => ':actor sizni «:title» o\'zgarishining ijrochisi qilib belgiladi (:date).',
        ],
    ],
    'open_change' => 'O\'zgarishni ochish',
    'maintenance' => [
        'due_soon' => [
            'subject' => 'Rejali xizmat: :title (:date)',
            'line' => ':asset uchun «:title» rejali xizmati muddati :date.',
        ],
        'overdue' => [
            'subject' => 'Rejali xizmat muddati o\'tdi: :title',
            'line' => ':asset uchun «:title» rejali xizmati :date gacha bajarilishi kerak edi.',
        ],
    ],
    'open_maintenance' => 'Rejali xizmatlarni ochish',
];
