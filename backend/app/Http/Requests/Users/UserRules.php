<?php

namespace App\Http\Requests\Users;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use App\Rules\AccessibleOrganization;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

/**
 * Validation shared by user store/update and access requests.
 */
final class UserRules
{
    /** Employee must be visible to the actor and not linked to another user. */
    public static function linkableEmployee(?int $ignoreUserId = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignoreUserId): void {
            if (Employee::query()->whereKey($value)->doesntExist()) {
                $fail(__('validation.exists', ['attribute' => __('validation.attributes.employee_id')]));

                return;
            }
            $linked = DB::table('users')->where('employee_id', $value)
                ->when($ignoreUserId !== null, fn ($q) => $q->where('id', '!=', $ignoreUserId))->exists();
            if ($linked) {
                $fail(__('messages.employee_already_linked'));
            }
        };
    }

    /** When both are given, the home organization must be the employee's organization. */
    public static function checkEmployeeOrganization(Validator $validator, mixed $employeeId, mixed $organizationId): void
    {
        if (empty($employeeId) || empty($organizationId) || $validator->errors()->has('employee_id')) {
            return;
        }
        $employeeOrganization = DB::table('employees')->where('id', $employeeId)->value('organization_id');
        if ((int) $employeeOrganization !== (int) $organizationId) {
            $validator->errors()->add('organization_id', __('messages.employee_org_mismatch'));
        }
    }

    /** @return array<string, list<mixed>> rules for an `access` list under $key */
    public static function accessRows(string $key): array
    {
        return [
            $key => ['array'],
            "{$key}.*.organization_id" => ['required', 'integer', 'distinct', new AccessibleOrganization(false)],
            "{$key}.*.include_descendants" => ['required', 'boolean'],
        ];
    }

    /** include_descendants requires the actor to cover the whole subtree. */
    public static function checkSubtreeCover(Validator $validator, User $actor, string $key, mixed $rows): void
    {
        foreach ((array) $rows as $index => $row) {
            if (! is_array($row) || empty($row['include_descendants']) || $validator->errors()->has("{$key}.{$index}.organization_id")) {
                continue;
            }
            $organization = Organization::query()->find($row['organization_id'] ?? null);
            if ($organization !== null && ! $actor->accessScope()->coversSubtreeOf($organization)) {
                $validator->errors()->add("{$key}.{$index}.include_descendants", __('messages.subtree_not_covered'));
            }
        }
    }
}
