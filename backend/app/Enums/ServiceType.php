<?php

namespace App\Enums;

enum ServiceType: string
{
    case Internet = 'internet';
    case Telephony = 'telephony';
    case Mobile = 'mobile';
    case DataCenter = 'data_center';
    case Software = 'software';
    case Other = 'other';

    public function label(): string
    {
        return __('enums.service_type.'.$this->value);
    }
}
