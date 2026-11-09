<?php

namespace App\Enums;

enum ChangeEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Started = 'started';
    case Implemented = 'implemented';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Revised = 'revised';
    case Commented = 'commented';

    public function label(): string
    {
        return __('enums.change_event.'.$this->value);
    }
}
