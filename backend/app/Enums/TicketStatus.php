<?php

namespace App\Enums;

enum TicketStatus: string
{
    case New = 'new';
    case InReview = 'in_review';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case WaitingUser = 'waiting_user';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Rejected = 'rejected';

    /** Still needs IT work (SLA clock relevant). */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Resolved, self::Closed, self::Rejected], true);
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return array_values(array_map(
            fn (self $s): string => $s->value,
            array_filter(self::cases(), fn (self $s): bool => $s->isOpen()),
        ));
    }

    public function label(): string
    {
        return __('enums.ticket_status.'.$this->value);
    }
}
