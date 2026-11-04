<?php

namespace App\Http\Requests\Auth;

use App\Http\Middleware\SetLocale;
use App\Notifications\Channels\ChannelResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'locale' => ['sometimes', 'string', Rule::in(SetLocale::SUPPORTED)],
            'notification_preferences' => ['sometimes', 'array:mail_muted'],
            'notification_preferences.mail_muted' => ['required_with:notification_preferences', 'array'],
            'notification_preferences.mail_muted.*' => ['string', 'distinct', Rule::in(ChannelResolver::TYPES)],
        ];
    }
}
