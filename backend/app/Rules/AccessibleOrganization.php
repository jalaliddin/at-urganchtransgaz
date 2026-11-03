<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The value must be an existing organization inside the current user's access
 * scope (and active, unless $mustBeActive is false).
 */
class AccessibleOrganization implements ValidationRule
{
    public function __construct(private readonly bool $mustBeActive = true) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = Auth::user();
        $id = filter_var($value, FILTER_VALIDATE_INT);

        if (! $user instanceof User || $id === false) {
            $fail('validation.accessible_organization')->translate();

            return;
        }

        $organization = DB::table('organizations')->where('id', $id)->whereNull('deleted_at')->first(['id', 'is_active']);

        if ($organization === null
            || ! $user->canAccessOrganization((int) $organization->id)
            || ($this->mustBeActive && ! $organization->is_active)) {
            $fail('validation.accessible_organization')->translate();
        }
    }
}
