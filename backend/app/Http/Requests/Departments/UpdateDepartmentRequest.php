<?php

namespace App\Http\Requests\Departments;

use App\Models\Department;
use App\Services\Departments\DepartmentService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->department());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $department = $this->department();

        return [
            'organization_id' => ['prohibited'],
            'parent_id' => [
                'nullable', 'integer',
                StoreDepartmentRequest::sameOrganization($department->organization_id),
                function (string $attribute, mixed $value, Closure $fail) use ($department): void {
                    if (app(DepartmentService::class)->wouldCreateCycle($department, (int) $value)) {
                        $fail(__('messages.department_cycle'));
                    }
                },
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('departments', 'code')->where('organization_id', $department->organization_id)->ignore($department->id),
            ],
            ...StoreDepartmentRequest::attributeRules(),
        ];
    }

    private function department(): Department
    {
        /** @var Department */
        return $this->route('department');
    }
}
