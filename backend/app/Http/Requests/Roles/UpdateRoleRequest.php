<?php

namespace App\Http\Requests\Roles;

use App\Models\Role;
use App\Support\Permissions\PermissionRegistry;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('role'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'name' => ['prohibited'],
            'display_name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            // Super Admin passes every check anyway; its permission list is not editable.
            'permissions' => [$role->name === Roles::SUPER_ADMIN ? 'prohibited' : 'sometimes', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(app(PermissionRegistry::class)->all())],
        ];
    }
}
