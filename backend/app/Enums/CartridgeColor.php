<?php

namespace App\Enums;

enum CartridgeColor: string
{
    case Black = 'black';
    case Cyan = 'cyan';
    case Magenta = 'magenta';
    case Yellow = 'yellow';
    case Tricolor = 'tricolor';
    case Other = 'other';

    public function label(): string
    {
        return __('enums.cartridge_color.'.$this->value);
    }
}
