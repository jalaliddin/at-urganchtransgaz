<?php

namespace App\Enums;

enum MaintenanceTaskStatus: string
{
    case Planned = 'planned';
    case Done = 'done';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('enums.maintenance_task_status.'.$this->value);
    }
}
