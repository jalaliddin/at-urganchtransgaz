<?php

namespace App\Enums;

enum PrinterConnection: string
{
    case Usb = 'usb';
    case Network = 'network';
    case Wifi = 'wifi';
    case Shared = 'shared';

    public function label(): string
    {
        return __('enums.printer_connection.'.$this->value);
    }
}
