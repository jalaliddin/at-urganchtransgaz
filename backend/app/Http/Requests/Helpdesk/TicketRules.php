<?php

namespace App\Http\Requests\Helpdesk;

use App\Enums\TicketPriority;
use Illuminate\Validation\Rule;

/** Validation rules shared by ticket create and update. */
final class TicketRules
{
    /** @return array<string, mixed> */
    public static function content(bool $required): array
    {
        $req = $required ? 'required' : 'sometimes';

        return [
            'contact_name' => ['nullable', 'string', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{5,30}$/'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'location' => ['nullable', 'string', 'max:150'],
            'category_id' => [$req, 'integer', Rule::exists('ticket_categories', 'id')->where('is_active', true)],
            'title' => [$req, 'string', 'min:5', 'max:200'],
            'description' => [$req, 'string', 'min:10', 'max:5000'],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
        ];
    }
}
