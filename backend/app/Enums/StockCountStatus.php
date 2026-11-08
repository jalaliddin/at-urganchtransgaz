<?php

namespace App\Enums;

enum StockCountStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
