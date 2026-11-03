<?php

namespace Tests\Concerns;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserOrganizationAccess;
use Database\Seeders\RbacSeeder;

/**
 * Helpers for creating users with roles and organization access in tests.
 */
trait InteractsWithAccess
{
    protected function seedRbac(): void
    {
        $this->seed(RbacSeeder::class);
    }

    /**
     * A user whose home organization is $organization and who may access it
     * (and its subtree when $descendants is true).
     */
    protected function makeUser(string $role, ?Organization $organization = null, bool $descendants = false): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create(['organization_id' => $organization?->id]);
        $user->assignRole($role);

        if ($organization !== null) {
            $this->grantAccess($user, $organization, $descendants);
        }

        return $user;
    }

    protected function grantAccess(User $user, Organization $organization, bool $descendants = false): void
    {
        UserOrganizationAccess::create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'include_descendants' => $descendants,
        ]);
    }
}
