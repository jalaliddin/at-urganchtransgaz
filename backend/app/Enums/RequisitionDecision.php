<?php

namespace App\Enums;

enum RequisitionDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
}
