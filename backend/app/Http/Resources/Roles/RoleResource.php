<?php

namespace App\Http\Resources\Roles;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Role $role */
        $role = $this->resource;

        return [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => $role->display_name,
            'description' => $role->description,
            'is_system' => $role->is_system,
            'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
            'users_count' => $this->when(
                array_key_exists('users_count', $role->getAttributes()),
                fn (): int => (int) $role->getAttribute('users_count'),
            ),
        ];
    }
}
