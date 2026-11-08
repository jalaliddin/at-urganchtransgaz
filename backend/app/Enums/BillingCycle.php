<?php

namespace App\Enums;

enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::Yearly => 12,
        };
    }

    public function label(): string
    {
        return __('enums.billing_cycle.'.$this->value);
    }
}
