<?php

namespace App\Enums;

enum ReplacementSource: string
{
    case Warehouse = 'warehouse';
    case Other = 'other';

    public function label(): string
    {
        return __('enums.replacement_source.'.$this->value);
    }
}
