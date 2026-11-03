<?php

namespace App\Http\Resources\Auth;

use App\Models\User;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user's profile, permissions and organization scope (for the SPA).
 *
 * @mixin User
 */
class MeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $user->loadMissing(['roles', 'organization', 'employee', 'organizationAccess.organization']);
        $isSuperAdmin = $user->isSuperAdmin();

        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale,
            'must_change_password' => $user->must_change_password,
            'password_expired' => $user->passwordExpired(),
            'is_super_admin' => $isSuperAdmin,
            'organization' => $user->organization ? ['id' => $user->organization->id, 'name' => $user->organization->name] : null,
            'employee' => $user->employee ? [
                'id' => $user->employee->id,
                'full_name' => $user->employee->full_name,
                'position' => $user->employee->position,
            ] : null,
            'roles' => $this->roles($user),
            'permissions' => $isSuperAdmin
                ? app(PermissionRegistry::class)->all()
                : $user->getAllPermissions()->pluck('name')->sort()->values(),
            'access' => $user->organizationAccess
                ->filter(fn ($row) => $row->organization !== null)
                ->map(fn ($row) => [
                    'organization_id' => $row->organization_id,
                    'organization_name' => $row->organization->name,
                    'include_descendants' => $row->include_descendants,
                ])->values(),
        ];
    }

    /** @return list<array{name: string, display_name: string}> */
    private function roles(User $user): array
    {
        $roles = [];
        foreach ($user->roles as $role) {
            $roles[] = [
                'name' => (string) $role->getAttribute('name'),
                'display_name' => (string) $role->getAttribute('display_name'),
            ];
        }

        return $roles;
    }
}
