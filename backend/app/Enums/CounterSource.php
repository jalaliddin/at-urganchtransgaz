<?php

namespace App\Enums;

enum CounterSource: string
{
    case Manual = 'manual';
    case Replacement = 'replacement';

    public function label(): string
    {
        return __('enums.counter_source.'.$this->value);
    }
}
