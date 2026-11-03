<?php

namespace App\Http\Requests\Auth;

use App\Services\Auth\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255', 'confirmed', app(PasswordRules::class)->make()],
        ];
    }
}
