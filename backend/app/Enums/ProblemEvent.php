<?php

namespace App\Enums;

enum ProblemEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case StatusChanged = 'status_changed';
    case Assigned = 'assigned';
    case TicketsLinked = 'tickets_linked';
    case TicketUnlinked = 'ticket_unlinked';
    case Commented = 'commented';

    public function label(): string
    {
        return __('enums.problem_event.'.$this->value);
    }
}
