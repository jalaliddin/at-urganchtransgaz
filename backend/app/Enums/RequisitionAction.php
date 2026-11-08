<?php

namespace App\Enums;

/** Requisition history entries. */
enum RequisitionAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Submitted = 'submitted';
    case ApprovalSkipped = 'approval_skipped';
    case OrgApproved = 'org_approved';
    case OrgRejected = 'org_rejected';
    case Returned = 'returned';
    case Taken = 'taken';
    case InfoRequested = 'info_requested';
    case InfoProvided = 'info_provided';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Ready = 'ready';
    case Allocated = 'allocated';
    case Completed = 'completed';
    case Received = 'received';
    case Closed = 'closed';
    case Commented = 'commented';
    case FileAdded = 'file_added';
}
