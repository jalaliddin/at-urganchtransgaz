<?php

namespace App\Enums;

enum AssetEventAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case StatusChanged = 'status_changed';
    case Assigned = 'assigned';
    case Returned = 'returned';
    case Transferred = 'transferred';
    case MaintenanceOpened = 'maintenance_opened';
    case MaintenanceClosed = 'maintenance_closed';
    case DisposalRequested = 'disposal_requested';
    case DisposalApproved = 'disposal_approved';
    case DisposalRejected = 'disposal_rejected';
    case RelationAdded = 'relation_added';
    case RelationRemoved = 'relation_removed';
    case AttachmentAdded = 'attachment_added';
    case AttachmentRemoved = 'attachment_removed';
    case InventoryFound = 'inventory_found';
    case ChangeImplemented = 'change_implemented';
    case PreventiveDone = 'preventive_done';
}
