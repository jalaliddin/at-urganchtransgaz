<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->jobTitle().' bo\'limi',
            'code' => 'D-'.fake()->unique()->numerify('####'),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
