<?php

namespace App\Enums;

enum TransferStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return $this === self::Draft || $this === self::Sent;
    }
}
