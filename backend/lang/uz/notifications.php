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
];
