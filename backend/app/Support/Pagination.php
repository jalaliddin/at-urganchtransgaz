<?php

namespace App\Support;

use Illuminate\Http\Request;

final class Pagination
{
    public const DEFAULT_PER_PAGE = 25;

    public const MAX_PER_PAGE = 100;

    /** Reads ?per_page, clamped to 1..100; anything non-numeric falls back to the default. */
    public static function perPage(Request $request): int
    {
        $value = $request->query('per_page');

        if (! is_string($value) || ! preg_match('/^-?\d+$/', $value)) {
            return self::DEFAULT_PER_PAGE;
        }

        return max(1, min(self::MAX_PER_PAGE, (int) $value));
    }
}
