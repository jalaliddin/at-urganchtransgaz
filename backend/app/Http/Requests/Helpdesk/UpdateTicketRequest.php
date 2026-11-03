<?php

namespace App\Http\Requests\Helpdesk;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');

        return $ticket instanceof Ticket && (bool) $this->user()?->can('update', $ticket);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Ticket $ticket */
        $ticket = $this->route('ticket');

        return [
            'department_id' => ['sometimes', 'nullable', 'integer', Rule::exists('departments', 'id')
                ->where('organization_id', $ticket->organization_id)->whereNull('deleted_at')],
            ...TicketRules::content(required: false),
        ];
    }
}
