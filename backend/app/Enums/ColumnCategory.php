<?php

namespace App\Enums;

/** What a column means for its tasks: still to do, finished, or dropped. */
enum ColumnCategory: string
{
    case Open = 'open';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function finishes(): bool
    {
        return $this !== self::Open;
    }

    public function label(): string
    {
        return __('enums.column_category.'.$this->value);
    }
}
