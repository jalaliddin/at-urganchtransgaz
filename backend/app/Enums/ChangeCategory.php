<?php

namespace App\Enums;

enum ChangeCategory: string
{
    case Network = 'network';
    case Server = 'server';
    case Configuration = 'configuration';
    case Software = 'software';
    case Other = 'other';

    public function label(): string
    {
        return __('enums.change_category.'.$this->value);
    }
}
