<?php

return [
    'failed' => 'Не удалось сформировать экспорт. Повторите попытку позже.',
    'pdf_too_large' => 'Слишком много строк для PDF (более :max). Сузьте фильтры или выберите Excel.',
    'sla_ok' => 'В срок',
    'sla_breached' => 'Просрочено',
    'helpdesk_tickets' => [
        'title' => 'Заявки Helpdesk',
        'columns' => [
            'number' => 'Номер',
            'created_at' => 'Создана',
            'title' => 'Заголовок',
            'organization' => 'Организация',
            'category' => 'Категория',
            'priority' => 'Приоритет',
            'status' => 'Статус',
            'requester' => 'Заявитель',
            'assignee' => 'Исполнитель',
            'response_due_at' => 'Срок реакции',
            'resolution_due_at' => 'Срок решения',
            'resolved_at' => 'Решена',
            'sla' => 'SLA',
            'time_spent' => 'Затрачено (мин.)',
            'rating' => 'Оценка',
        ],
    ],
];
