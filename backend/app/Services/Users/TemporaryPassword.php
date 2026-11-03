<?php

namespace App\Services\Users;

use App\Services\Settings\SettingsService;

/**
 * One-time passwords handed out by administrators. No look-alike characters
 * (0/O, 1/l/I); always contains letters and digits; never shorter than the policy.
 */
class TemporaryPassword
{
    private const LETTERS = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';

    private const DIGITS = '23456789';

    public function __construct(private readonly SettingsService $settings) {}

    public function generate(): string
    {
        $length = max(14, (int) $this->settings->get('security.password_min_length'));
        $alphabet = self::LETTERS.self::DIGITS;

        $chars = [self::pick(self::LETTERS), self::pick(self::DIGITS)];
        while (count($chars) < $length) {
            $chars[] = self::pick($alphabet);
        }

        // Fisher–Yates with a CSPRNG so the guaranteed characters are not always first.
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    private static function pick(string $alphabet): string
    {
        return $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
}
