<?php

namespace App\Enums;

enum EmployeeStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case BusinessTrip = 'business_trip';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return __('enums.employee_status.'.$this->value);
    }
}
