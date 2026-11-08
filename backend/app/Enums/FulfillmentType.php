<?php

namespace App\Enums;

enum FulfillmentType: string
{
    case Stock = 'stock';
    case Asset = 'asset';
    case Note = 'note';
}
