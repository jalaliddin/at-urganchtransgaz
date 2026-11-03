<?php

namespace App\Http\Requests\Employees;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Rules\AccessibleOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->employee());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $employee = $this->employee();
        $organizationId = $this->has('organization_id') ? $this->integer('organization_id') : $employee->organization_id;

        return [
            ...EmployeeRules::attributes(),
            // A transfer needs access to the target too (the source is guaranteed by the scope).
            'organization_id' => ['sometimes', 'integer', new AccessibleOrganization],
            'department_id' => ['nullable', 'integer', EmployeeRules::departmentIn($organizationId)],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'first_name' => ['sometimes', 'string', 'max:100'],
            'personnel_number' => ['nullable', 'string', 'max:50',
                Rule::unique('employees', 'personnel_number')->where('organization_id', $organizationId)->ignore($employee->id)],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $employee = $this->employee();
            $status = $this->has('status') ? $this->input('status') : $employee->status->value;
            $dismissedAt = $this->has('dismissed_at') ? $this->input('dismissed_at') : $employee->getAttribute('dismissed_at');

            if ($status === EmployeeStatus::Dismissed->value && empty($dismissedAt)) {
                $validator->errors()->add('dismissed_at', __('validation.required_if', [
                    'attribute' => __('validation.attributes.dismissed_at'),
                    'other' => __('validation.attributes.status'),
                    'value' => EmployeeStatus::Dismissed->label(),
                ]));
            }
        }];
    }

    private function employee(): Employee
    {
        /** @var Employee */
        return $this->route('employee');
    }
}
