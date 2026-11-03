<?php

namespace App\Http\Requests\Organizations;

use App\Models\Employee;
use App\Models\Organization;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItResponsibleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manageItStaff', $this->route('organization'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Organization $organization */
        $organization = $this->route('organization');

        return [
            'employee_id' => [
                'required', 'integer',
                // Scoped lookup: an employee outside the actor's organizations does not "exist".
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (Employee::query()->whereKey($value)->doesntExist()) {
                        $fail(__('validation.exists', ['attribute' => __('validation.attributes.employee_id')]));
                    }
                },
                Rule::unique('organization_it_responsibles', 'employee_id')
                    ->where('organization_id', $organization->id),
            ],
            'is_primary' => ['sometimes', 'boolean'],
            'assigned_at' => ['sometimes', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['employee_id.unique' => __('messages.it_responsible_exists')];
    }
}
