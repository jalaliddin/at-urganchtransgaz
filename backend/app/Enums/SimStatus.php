<?php

namespace App\Enums;

enum SimStatus: string
{
    case InStock = 'in_stock';
    case Active = 'active';
    case Blocked = 'blocked';
    case Cancelled = 'cancelled';
    case Returned = 'returned';

    public function label(): string
    {
        return __('enums.sim_status.'.$this->value);
    }

    /** Free to be assigned to an employee, moved to another organization or allocated to a requisition. */
    public function isAvailable(): bool
    {
        return $this === self::InStock || $this === self::Returned;
    }
}
