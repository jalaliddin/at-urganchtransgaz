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
    'open_disposals' => 'Запросы на списание',
    'open_assets' => 'Открыть активы',
    'asset_disposal_requested' => [
        'subject' => 'Запрошено списание актива :number',
        'line' => 'Запрос на списание актива :number — :name ожидает решения.',
    ],
    'asset_warranty_expiring' => [
        'subject' => ':organization: истекает гарантия :count активов',
        'line' => 'В организации :organization гарантия :count активов истекает в течение :days дней.',
    ],
    'stock_low' => [
        'subject' => ':warehouse: :material ниже минимального остатка',
        'line' => 'Остаток :material на складе :warehouse — :quantity :unit (минимум — :minimum :unit).',
    ],
    'stock_expiring' => [
        'subject' => 'Истекает срок годности у партий: :count',
        'line' => 'Срок годности :count партий истекает в течение :days дней:',
    ],
    'open_warehouse' => 'Открыть склад',
    'requisition' => [
        'org_approval' => [
            'subject' => 'Заявка :number ждёт вашего согласования',
            'line' => ':organization: заявка «:title» ждёт согласования руководителя организации.',
        ],
        'queued' => [
            'subject' => 'Заявка :number поступила в центральную ИТ-службу',
            'line' => ':organization: заявка «:title» ждёт рассмотрения.',
        ],
        'info_requested' => [
            'subject' => ':number: запрошена дополнительная информация',
            'line' => 'Центральная ИТ-служба запросила дополнительную информацию по заявке «:title».',
        ],
        'info_provided' => [
            'subject' => ':number: информация предоставлена',
            'line' => 'По заявке «:title» предоставлена запрошенная информация.',
        ],
        'approved' => [
            'subject' => 'Заявка :number утверждена',
            'line' => 'Заявка «:title» утверждена руководителем центральной ИТ-службы.',
        ],
        'allocation' => [
            'subject' => ':number: нужно выделить ресурсы',
            'line' => ':organization: заявка «:title» утверждена, выделите ресурсы.',
        ],
        'rejected' => [
            'subject' => 'Заявка :number отклонена',
            'line' => 'Заявка «:title» отклонена.',
        ],
        'returned' => [
            'subject' => 'Заявка :number возвращена на доработку',
            'line' => 'Исправьте заявку «:title» и отправьте снова.',
        ],
        'partially_fulfilled' => [
            'subject' => ':number: ресурсы выделены частично',
            'line' => 'По заявке «:title» выделена часть ресурсов.',
        ],
        'fulfilled' => [
            'subject' => ':number: ресурсы выделены — подтвердите получение',
            'line' => 'По заявке «:title» ресурсы выделены полностью. Подтвердите получение.',
        ],
        'received' => [
            'subject' => ':number: ресурсы получены',
            'line' => ':organization подтвердила получение ресурсов по заявке «:title».',
        ],
    ],
    'open_requisition' => 'Открыть заявку',
    'license_expiring' => [
        'subject' => 'Истекает лицензия :software (:date)',
        'line' => ':organization: лицензия :software (:number) истекает :date.',
    ],
    'open_licenses' => 'Открыть лицензии',
    'service_payment' => [
        'due_soon' => [
            'subject' => 'Скоро срок оплаты: :service (:date)',
            'line' => ':organization: :provider — по услуге «:service» нужно оплатить :amount сум до :date.',
        ],
        'overdue' => [
            'subject' => 'Оплата просрочена: :service (:date)',
            'line' => ':organization: :provider — по услуге «:service» :amount сум нужно было оплатить :date.',
        ],
    ],
    'open_invoice' => 'Открыть счёт',
    'contract_expiring' => [
        'subject' => 'Договор :number заканчивается (:date)',
        'line' => ':organization: договор :number с :provider заканчивается :date.',
    ],
    'open_contract' => 'Открыть договор',
    'task' => [
        'assigned' => [
            'subject' => 'Задача :number назначена вам',
            'line' => ':actor назначил(а) вам задачу «:title».',
        ],
        'commented' => [
            'subject' => 'Комментарий к задаче :number',
            'line' => ':actor оставил(а) комментарий к задаче «:title».',
        ],
        'due_soon' => [
            'subject' => 'Срок задачи :number завтра (:date)',
            'line' => 'Срок задачи «:title» — :date.',
        ],
        'overdue' => [
            'subject' => 'Задача :number просрочена (:date)',
            'line' => 'Задачу «:title» нужно было выполнить до :date.',
        ],
    ],
    'open_task' => 'Открыть задачу',
    'problem' => [
        'assigned' => [
            'subject' => 'Вам назначена проблема :number',
            'line' => ':actor назначил(а) вам проблему «:title».',
        ],
        'resolved' => [
            'subject' => 'Проблема :number решена',
            'line' => 'Проблема «:title» решена. Связанную заявку можно закрыть.',
        ],
    ],
    'open_problem' => 'Открыть проблему',
    'change' => [
        'approval' => [
            'subject' => 'Изменение :number ждёт согласования',
            'line' => ':actor отправил(а) на согласование изменение «:title» (:date).',
        ],
        'emergency' => [
            'subject' => 'Экстренное изменение :number',
            'line' => ':actor зарегистрировал(а) экстренное изменение «:title» (:date).',
        ],
        'approved' => [
            'subject' => 'Изменение :number согласовано',
            'line' => ':actor согласовал(а) изменение «:title».',
        ],
        'rejected' => [
            'subject' => 'Изменение :number отклонено',
            'line' => ':actor отклонил(а) изменение «:title».',
        ],
        'assigned' => [
            'subject' => 'Вы исполнитель изменения :number',
            'line' => ':actor назначил(а) вас исполнителем изменения «:title» (:date).',
        ],
    ],
    'open_change' => 'Открыть изменение',
    'maintenance' => [
        'due_soon' => [
            'subject' => 'Плановое обслуживание: :title (:date)',
            'line' => 'Плановое обслуживание «:title» для :asset — срок :date.',
        ],
        'overdue' => [
            'subject' => 'Просрочено плановое обслуживание: :title',
            'line' => 'Плановое обслуживание «:title» для :asset нужно было выполнить до :date.',
        ],
    ],
    'open_maintenance' => 'Открыть плановое обслуживание',
];
