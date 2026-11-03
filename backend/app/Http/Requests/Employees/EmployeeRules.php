<?php

namespace App\Http\Requests\Employees;

use App\Enums\EmployeeStatus;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Validation shared by the employee store/update requests.
 */
final class EmployeeRules
{
    /** @return array<string, list<mixed>> */
    public static function attributes(): array
    {
        return [
            'middle_name' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^\+?[0-9\s\-()]{5,20}$/'],
            'internal_phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'hired_at' => ['nullable', 'date_format:Y-m-d'],
            'dismissed_at' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::enum(EmployeeStatus::class)],
        ];
    }

    /** The department must exist (not deleted) in the given organization. */
    public static function departmentIn(int $organizationId): Closure
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
