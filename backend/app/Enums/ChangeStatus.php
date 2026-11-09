<?php

namespace App\Enums;

enum ChangeStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case InProgress = 'in_progress';
    case Implemented = 'implemented';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /** Approved or running: the change holds its window (conflicts, calendar). */
    public function isScheduled(): bool
    {
        return in_array($this, [self::Approved, self::InProgress], true);
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Implemented, self::Failed, self::Cancelled], true);
    }

    public function label(): string
    {
        return __('enums.change_status.'.$this->value);
    }
}
