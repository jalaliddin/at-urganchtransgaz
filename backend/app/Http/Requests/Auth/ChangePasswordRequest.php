<?php

namespace App\Http\Requests\Auth;

use App\Services\Auth\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'max:255', 'confirmed', 'different:current_password', app(PasswordRules::class)->make()],
        ];
    }
}
