<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent. Creates missing permissions and roles. Existing roles only receive
 * default permissions created in *this* run, so a Super Admin's edits survive.
 */
class RbacSeeder extends Seeder
{
    private const GUARD = 'web';

    public function run(PermissionRegistry $registry, PermissionRegistrar $registrar): void
    {
        $registrar->forgetCachedPermissions();

        $existing = Permission::query()->where('guard_name', self::GUARD)->pluck('name')->all();
        $new = array_values(array_diff($registry->all(), $existing));
        foreach ($new as $name) {
            Permission::create(['name' => $name, 'guard_name' => self::GUARD]);
        }

        foreach ($registry->defaultRoles() as $name => $definition) {
            $role = Role::query()->where('name', $name)->where('guard_name', self::GUARD)->first();

            if ($role === null) {
                $role = Role::create([
                    'name' => $name,
                    'guard_name' => self::GUARD,
                    'display_name' => $definition['display_name'],
                    'description' => $definition['description'],
                    'is_system' => true,
                ]);
                $role->syncPermissions($definition['permissions']);

                continue;
            }

            $grant = array_values(array_intersect($definition['permissions'], $new));
            if ($grant !== []) {
                $role->givePermissionTo($grant);
            }
        }

        $registrar->forgetCachedPermissions();
    }
}
