<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact decimal arithmetic on strings (bcmath) for quantities (scale 3) and money
 * (scale 2). Never use floats for stock.
 */
final class Decimal
{
    /** Normalizes request/DB input (int, float or numeric string) to a fixed scale, rounding half away from zero. */
    public static function of(mixed $value, int $scale = 3): string
    {
        if (is_int($value)) {
            return bcadd((string) $value, '0', $scale);
        }
        if (is_float($value)) {
            return number_format($value, $scale, '.', '');
        }
        if (! is_string($value) || ! preg_match('/^-?\d+(\.\d+)?$/', trim($value))) {
            throw new InvalidArgumentException('Not a decimal number.');
        }

        return self::round(trim($value), $scale);
    }

    public static function isNumeric(mixed $value): bool
    {
        return is_int($value) || (is_float($value) && is_finite($value))
            || (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', trim($value)) === 1);
    }

    public static function round(string $value, int $scale): string
    {
        $offset = '0.'.str_repeat('0', $scale).'5';

        return str_starts_with($value, '-') ? bcsub($value, $offset, $scale) : bcadd($value, $offset, $scale);
    }

    public static function add(string $a, string $b, int $scale = 3): string
    {
        return bcadd($a, $b, $scale);
    }

    public static function sub(string $a, string $b, int $scale = 3): string
    {
        return bcsub($a, $b, $scale);
    }

    /** Product rounded to $scale. */
    public static function mul(string $a, string $b, int $scale = 2): string
    {
        return self::round(bcmul($a, $b, $scale + 4), $scale);
    }

    /** Quotient rounded to $scale; $b must not be zero. */
    public static function div(string $a, string $b, int $scale = 2): string
    {
        return self::round(bcdiv($a, $b, $scale + 4), $scale);
    }

    public static function cmp(string $a, string $b, int $scale = 3): int
    {
        return bccomp($a, $b, $scale);
    }

    public static function min(string $a, string $b, int $scale = 3): string
    {
        return bccomp($a, $b, $scale) <= 0 ? $a : $b;
    }

    public static function abs(string $value): string
    {
        return ltrim($value, '-');
    }

    public static function neg(string $value): string
    {
        return str_starts_with($value, '-') ? substr($value, 1) : '-'.$value;
    }

    /** "3.500" → "3.5", "4.000" → "4" (for messages). */
    public static function plain(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }
}
