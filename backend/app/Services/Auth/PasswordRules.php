<?php

namespace App\Services\Auth;

use App\Services\Settings\SettingsService;
use Illuminate\Validation\Rules\Password;

/**
 * Password policy: minimum length from settings (security.password_min_length),
 * at least one letter and one digit.
 */
class PasswordRules
{
    public function __construct(private readonly SettingsService $settings) {}

    public function make(): Password
    {
        return Password::min((int) $this->settings->get('security.password_min_length'))
            ->letters()
            ->numbers();
    }
}
