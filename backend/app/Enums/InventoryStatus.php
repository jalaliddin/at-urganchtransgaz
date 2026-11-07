<?php

namespace App\Enums;

enum InventoryStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
