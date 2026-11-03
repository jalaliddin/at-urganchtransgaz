<?php

namespace App\Http\Requests\Users;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use App\Rules\AccessibleOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->target());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $target = $this->target();
        /** @var User $actor */
        $actor = $this->user();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
            'locale' => ['sometimes', Rule::in(SetLocale::SUPPORTED)],
            'employee_id' => ['nullable', 'integer', UserRules::linkableEmployee($target->id)],
            // Accounts without an organization are system accounts: unrestricted users only.
            'organization_id' => [
                'sometimes',
                Rule::requiredIf(fn (): bool => ! $actor->accessScope()->isUnrestricted()),
                'nullable', 'integer', new AccessibleOrganization,
            ],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            UserRules::checkEmployeeOrganization($validator, $this->input('employee_id'), $this->input('organization_id'));
        }];
    }

    private function target(): User
    {
        /** @var User */
        return $this->route('user');
    }
}
