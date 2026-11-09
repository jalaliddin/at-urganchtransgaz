<?php

namespace App\Enums;

enum MaintenanceResult: string
{
    case Ok = 'ok';
    case Issues = 'issues';

    public function label(): string
    {
        return __('enums.maintenance_result.'.$this->value);
    }
}
