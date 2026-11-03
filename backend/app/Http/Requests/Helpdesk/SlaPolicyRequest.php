<?php

namespace App\Http\Requests\Helpdesk;

use App\Enums\TicketPriority;
use App\Models\TicketSlaPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SlaPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('helpdesk.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'priority' => [$creating ? 'required' : 'prohibited', Rule::enum(TicketPriority::class)],
            'category_id' => [$creating ? 'nullable' : 'prohibited', 'integer', Rule::exists('ticket_categories', 'id')],
            'response_minutes' => ['required', 'integer', 'min:1', 'max:525600'],
            'resolution_minutes' => ['required', 'integer', 'min:1', 'max:525600', 'gte:response_minutes'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->isMethod('POST') || $validator->errors()->has('priority')) {
                return;
            }
            $exists = TicketSlaPolicy::query()
                ->where('priority', $this->input('priority'))
                ->when(
                    $this->filled('category_id'),
                    fn ($q) => $q->where('category_id', $this->integer('category_id')),
                    fn ($q) => $q->whereNull('category_id'),
                )->exists();
            if ($exists) {
                $validator->errors()->add('priority', __('messages.sla_policy_exists'));
            }
        }];
    }
}
