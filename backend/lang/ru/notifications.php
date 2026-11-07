<?php

return [
    'open_ticket' => 'Открыть заявку',
    'ticket_created' => [
        'subject' => 'Новая заявка :number',
        'line' => 'Поступила новая заявка от организации :organization (приоритет: :priority).',
    ],
    'ticket_assigned' => [
        'subject' => 'Вам назначена заявка :number',
        'line' => ':actor назначил(а) вас исполнителем этой заявки.',
    ],
    'ticket_status_changed' => [
        'subject' => 'Статус заявки :number: :status',
        'line' => 'Статус вашей заявки изменён: :status.',
    ],
    'ticket_commented' => [
        'subject' => 'Новый комментарий к заявке :number',
        'line' => ':actor оставил(а) комментарий к заявке.',
    ],
    'ticket_sla_breached' => [
        'subject' => 'Срок по заявке :number нарушен',
        'line' => 'Нарушен срок :kind по заявке.',
    ],
    'ticket_due_soon' => [
        'subject' => 'Срок по заявке :number скоро истекает',
        'line' => 'Срок решения заявки: :due_at.',
    ],
    'sla_kind' => [
        'response' => 'реакции',
        'resolution' => 'решения',
    ],
    'open_exports' => 'Открыть экспорты',
    'export_ready' => [
        'subject' => 'Экспорт «:report» готов',
        'line' => 'Отчёт «:report» готов к скачиванию.',
    ],
    'open_employee' => 'Открыть карточку сотрудника',
    'assets_return_required' => [
        'subject' => ':employee уволен(а) — верните активы',
        'line' => ':employee уволен(а), за ним(ней) числится активов: :count.',
    ],
    'open_transfer' => 'Открыть передачу',
    'asset_transfer_incoming' => [
        'subject' => ':number: активы отправлены в вашу организацию',
        'line' => 'Организация :from отправила активы. Проверьте и примите их.',
    ],
];
