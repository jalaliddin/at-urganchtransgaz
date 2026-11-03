<?php

namespace App\Enums;

enum OrganizationType: string
{
    case HeadOffice = 'head_office';
    case Subsidiary = 'subsidiary';
    case Branch = 'branch';
    case Other = 'other';

    public function label(): string
    {
        return __('enums.organization_type.'.$this->value);
    }
}
