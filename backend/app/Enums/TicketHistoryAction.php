<?php

namespace App\Enums;

enum TicketHistoryAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case StatusChanged = 'status_changed';
    case Assigned = 'assigned';
    case Commented = 'commented';
    case AttachmentAdded = 'attachment_added';
    case AttachmentRemoved = 'attachment_removed';
    case TimeLogged = 'time_logged';
    case Rated = 'rated';
    case Linked = 'linked';
    case Unlinked = 'unlinked';
    case SlaBreached = 'sla_breached';
    case AutoClosed = 'auto_closed';
}
