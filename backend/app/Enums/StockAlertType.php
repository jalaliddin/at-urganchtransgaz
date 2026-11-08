<?php

namespace App\Enums;

enum StockAlertType: string
{
    case LowStock = 'low_stock';
    case Expiring = 'expiring';
}
