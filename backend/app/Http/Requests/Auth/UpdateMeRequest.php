<?php

namespace App\Http\Requests\Auth;

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['locale' => ['required', 'string', Rule::in(SetLocale::SUPPORTED)]];
    }
}
