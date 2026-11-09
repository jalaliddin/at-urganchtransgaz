<?php

namespace App\Enums;

enum MaintenanceUnit: string
{
    case Week = 'week';
    case Month = 'month';

    public function label(): string
    {
        return __('enums.maintenance_unit.'.$this->value);
    }
}
