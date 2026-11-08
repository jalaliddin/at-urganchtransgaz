<?php

namespace App\Enums;

/** Requisition workflow (spec §5.10, design R2). */
enum RequisitionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case OrgApproval = 'org_approval';
    case CentralReview = 'central_review';
    case InfoRequested = 'info_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ReadyForAllocation = 'ready_for_allocation';
    case PartiallyFulfilled = 'partially_fulfilled';
    case Fulfilled = 'fulfilled';
    case Received = 'received';
    case Closed = 'closed';

    public function isTerminal(): bool
    {
        return $this === self::Rejected || $this === self::Closed;
    }

    /** Resources may be allocated against the approved quantities. */
    public function allowsAllocation(): bool
    {
        return in_array($this, [self::Approved, self::ReadyForAllocation, self::PartiallyFulfilled], true);
    }

    /** Waiting for the central head's decision. */
    public function awaitsCentralDecision(): bool
    {
        return $this === self::Submitted || $this === self::CentralReview;
    }

    public function label(): string
    {
        return __('enums.requisition_status.'.$this->value);
    }
}
