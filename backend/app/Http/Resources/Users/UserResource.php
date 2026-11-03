<?php

namespace App\Http\Resources\Users;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    use FormatsDates;

    public const RELATIONS = ['roles', 'organization', 'employee', 'organizationAccess.organization'];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $actor = $request->user();
        $isSelf = $actor !== null && $actor->is($user);

        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale,
            'is_active' => $user->is_active,
            'must_change_password' => $user->must_change_password,
            'last_login_at' => $this->iso($user->last_login_at),
            'organization' => $user->organization ? ['id' => $user->organization->id, 'name' => $user->organization->name] : null,
            'employee' => $user->employee ? ['id' => $user->employee->id, 'full_name' => $user->employee->full_name] : null,
            'roles' => $user->roles->map(fn ($role): array => [
                'name' => (string) $role->getAttribute('name'),
                'display_name' => (string) $role->getAttribute('display_name'),
            ])->values()->all(),
            // Rows for organizations the viewer cannot see are omitted.
            'access' => $user->organizationAccess
                ->filter(fn ($row): bool => $row->organization !== null)
                ->map(fn ($row): array => [
                    'organization_id' => $row->organization_id,
                    'organization_name' => $row->organization->name,
                    'include_descendants' => $row->include_descendants,
                ])->values()->all(),
            'created_at' => $this->iso($user->created_at),
            'can' => [
                'update' => (bool) $actor?->can('update', $user),
                'deactivate' => ! $isSelf && (bool) $actor?->can('deactivate', $user),
                'reset_password' => ! $isSelf && (bool) $actor?->can('resetPassword', $user),
                'manage_access' => ! $isSelf && (bool) $actor?->can('manageAccess', $user),
            ],
        ];
    }
}
