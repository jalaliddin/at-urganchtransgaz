<?php

namespace App\Enums;

enum CartridgeKind: string
{
    case Toner = 'toner';
    case Ink = 'ink';
    case Drum = 'drum';
    case Ribbon = 'ribbon';
    case Other = 'other';

    public function label(): string
    {
        return __('enums.cartridge_kind.'.$this->value);
    }
}
