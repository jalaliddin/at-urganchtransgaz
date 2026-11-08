<?php

namespace App\Enums;

use App\Support\Decimal;
use Carbon\CarbonImmutable;

enum InvoiceStatus: string
{
    case Planned = 'planned';
    case AwaitingInvoice = 'awaiting_invoice';
    case Due = 'due';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';

    public function label(): string
    {
        return __('enums.invoice_status.'.$this->value);
    }

    /**
     * The one place the payment status is decided (lists, dashboard, reports and reminders read the stored result):
     * fully paid invoice → paid; due date passed → overdue; some money paid → partially paid; invoice entered → due;
     * period started → awaiting invoice; otherwise planned.
     */
    public static function derive(CarbonImmutable $periodStart, CarbonImmutable $dueDate, ?string $amount, string $paid, CarbonImmutable $today): self
    {
        $today = $today->startOfDay();
        if ($amount !== null && Decimal::cmp($paid, $amount, 2) >= 0) {
            return self::Paid;
        }
        if ($dueDate->startOfDay()->lt($today)) {
            return self::Overdue;
        }
        if (Decimal::cmp($paid, '0', 2) > 0) {
            return self::PartiallyPaid;
        }
        if ($amount !== null) {
            return self::Due;
        }

        return $periodStart->startOfDay()->lte($today) ? self::AwaitingInvoice : self::Planned;
    }

    /** Statuses that still expect money. */
    public function isOpen(): bool
    {
        return $this !== self::Paid;
    }
}
