<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use App\Models\UserOrganizationAccess;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserOrganizationAccess>
 */
class UserOrganizationAccessFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'include_descendants' => false,
        ];
    }
}
