<?php

namespace App\Enums;

enum ChangeType: string
{
    case Standard = 'standard';
    case Normal = 'normal';
    case Emergency = 'emergency';

    public function label(): string
    {
        return __('enums.change_type.'.$this->value);
    }
}
