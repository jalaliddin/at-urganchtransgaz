<?php

namespace App\Enums;

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

    /** Statuses that still expect money. */
    public function isOpen(): bool
    {
        return $this !== self::Paid;
    }
}
