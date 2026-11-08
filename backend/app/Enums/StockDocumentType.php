<?php

namespace App\Enums;

/** Posted stock document kinds (spec §5.9). */
enum StockDocumentType: string
{
    case Receipt = 'receipt';
    case Issue = 'issue';
    case Return = 'return';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';

    /** Number prefix: RC-2026-00001. */
    public function prefix(): string
    {
        return match ($this) {
            self::Receipt => 'RC',
            self::Issue => 'IS',
            self::Return => 'RT',
            self::Transfer => 'TF',
            self::Adjustment => 'AD',
        };
    }

    /** Permission needed to post a document of this type by hand. */
    public function permission(): string
    {
        return match ($this) {
            self::Receipt, self::Return => 'warehouse.receive',
            self::Issue => 'warehouse.issue',
            self::Transfer => 'warehouse.transfer',
            self::Adjustment => 'warehouse.manage',
        };
    }
}
