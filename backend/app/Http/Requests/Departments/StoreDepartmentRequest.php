<?php

namespace App\Http\Requests\Departments;

use App\Models\Department;
use App\Rules\AccessibleOrganization;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Department::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $organizationId = $this->integer('organization_id');

        return [
            'organization_id' => ['required', 'integer', new AccessibleOrganization],
            'parent_id' => ['nullable', 'integer', self::sameOrganization($organizationId)],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('departments', 'code')->where('organization_id', $organizationId)],
            ...self::attributeRules(),
        ];
    }

    /** @return array<string, list<string>> */
    public static function attributeRules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^\+?[0-9\s\-()]{3,50}$/'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }

    /** The parent department must exist (not deleted) in the same organization. */
    public static function sameOrganization(int $organizationId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($organizationId): void {
            $exists = DB::table('departments')->where('id', $value)
                ->where('organization_id', $organizationId)->whereNull('deleted_at')->exists();
            if (! $exists) {
                $fail(__('messages.department_wrong_organization'));
            }
        };
    }
}
