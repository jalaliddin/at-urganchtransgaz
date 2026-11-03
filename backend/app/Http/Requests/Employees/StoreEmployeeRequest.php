<?php

namespace App\Http\Requests\Employees;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Rules\AccessibleOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Employee::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $organizationId = $this->integer('organization_id');

        return [
            ...EmployeeRules::attributes(),
            'organization_id' => ['required', 'integer', new AccessibleOrganization],
            'department_id' => ['nullable', 'integer', EmployeeRules::departmentIn($organizationId)],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            // Unique per organization, including soft-deleted rows (matches the DB index).
            'personnel_number' => ['nullable', 'string', 'max:50',
                Rule::unique('employees', 'personnel_number')->where('organization_id', $organizationId)],
            'status' => ['required', Rule::enum(EmployeeStatus::class)],
            'dismissed_at' => ['nullable', 'date_format:Y-m-d', 'required_if:status,dismissed'],
        ];
    }
}
