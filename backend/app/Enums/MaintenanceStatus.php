<?php

namespace App\Enums;

enum MaintenanceStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
