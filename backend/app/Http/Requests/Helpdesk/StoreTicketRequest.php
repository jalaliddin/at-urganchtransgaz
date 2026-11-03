<?php

namespace App\Http\Requests\Helpdesk;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\Ticket;
use App\Models\User;
use App\Rules\AccessibleOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Ticket::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'organization_id' => ['sometimes', 'integer', new AccessibleOrganization],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')
                ->where('organization_id', $this->organizationId() ?? 0)->whereNull('deleted_at')],
            'requester_employee_id' => ['nullable', 'integer'],
            ...TicketRules::content(required: true),
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var User $user */
            $user = $this->user();
            $organizationId = $this->organizationId();

            if ($organizationId === null) {
                $validator->errors()->add('organization_id', __('validation.required', ['attribute' => 'organization_id']));

                return;
            }
            if (! $this->has('organization_id') && ! $user->canAccessOrganization($organizationId)) {
                $validator->errors()->add('organization_id', __('validation.accessible_organization'));
            }

            if (! $this->filled('requester_employee_id')) {
                return;
            }
            $employeeId = $this->integer('requester_employee_id');
            if ($employeeId !== $user->employee_id && ! $user->can('helpdesk.process')) {
                $validator->errors()->add('requester_employee_id', __('messages.ticket_requester_forbidden'));

                return;
            }
            $employee = Employee::withoutGlobalScopes()->whereNull('deleted_at')->find($employeeId);
            if ($employee === null || $employee->organization_id !== $organizationId || $employee->status === EmployeeStatus::Dismissed) {
                $validator->errors()->add('requester_employee_id', __('messages.ticket_requester_invalid'));
            }
        }];
    }

    public function organizationId(): ?int
    {
        $value = $this->has('organization_id') ? $this->input('organization_id') : $this->user()?->organization_id;
        $id = filter_var($value, FILTER_VALIDATE_INT);

        return $id === false ? null : $id;
    }

    /** @return array<string, mixed> */
    public function ticketData(): array
    {
        return [...$this->validated(), 'organization_id' => $this->organizationId()];
    }
}
