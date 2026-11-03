<?php

namespace App\Http\Resources\Concerns;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * API date formats: timestamps as ISO 8601 UTC with Z, dates as YYYY-MM-DD.
 */
trait FormatsDates
{
    protected function iso(?DateTimeInterface $value): ?string
    {
        return $value === null ? null : Carbon::instance($value)->utc()->toIso8601ZuluString();
    }

    protected function date(?DateTimeInterface $value): ?string
    {
        return $value?->format('Y-m-d');
    }
}
