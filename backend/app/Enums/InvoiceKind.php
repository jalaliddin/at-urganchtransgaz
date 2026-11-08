<?php

namespace App\Enums;

enum InvoiceKind: string
{
    /** Generated for each billing period of a service. */
    case Regular = 'regular';
    /** One-off additional cost entered by hand. */
    case Extra = 'extra';

    public function label(): string
    {
        return __('enums.invoice_kind.'.$this->value);
    }
}
