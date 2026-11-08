<?php

namespace App\Enums;

enum SimEvent: string
{
    case Registered = 'registered';
    case Updated = 'updated';
    case Assigned = 'assigned';
    case Returned = 'returned';
    case Blocked = 'blocked';
    case Unblocked = 'unblocked';
    case Transferred = 'transferred';
    case Allocated = 'allocated';
    case Cancelled = 'cancelled';
    case Replaced = 'replaced';

    public function label(): string
    {
        return __('enums.sim_event.'.$this->value);
    }
}
