<?php

namespace App\Enums;

enum RequisitionItemKind: string
{
    case Equipment = 'equipment';
    case Network = 'network';
    case Consumable = 'consumable';
    case Sim = 'sim';
    case Internet = 'internet';
    case Software = 'software';
    case Repair = 'repair';
    case Other = 'other';

    public function label(): string
    {
        return __('enums.requisition_item_kind.'.$this->value);
    }
}
