<?php

namespace App\Http\Requests\Users;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use App\Rules\AccessibleOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', User::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var User $actor */
        $actor = $this->user();

        return [
            'username' => ['required', 'string', 'regex:/^[a-z0-9._-]{3,64}$/', Rule::unique('users', 'username')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'locale' => ['sometimes', Rule::in(SetLocale::SUPPORTED)],
            'employee_id' => ['nullable', 'integer', UserRules::linkableEmployee()],
            // System accounts without an organization can only be created by unrestricted users.
            'organization_id' => [
                Rule::requiredIf(fn (): bool => ! $this->filled('employee_id') && ! $actor->accessScope()->isUnrestricted()),
                'nullable', 'integer', new AccessibleOrganization,
            ],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'distinct', Rule::exists('roles', 'name')],
            ...UserRules::accessRows('organization_access'),
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            UserRules::checkEmployeeOrganization($validator, $this->input('employee_id'), $this->input('organization_id'));
            /** @var User $actor */
            $actor = $this->user();
            UserRules::checkSubtreeCover($validator, $actor, 'organization_access', $this->input('organization_access', []));
        }];
    }
}
