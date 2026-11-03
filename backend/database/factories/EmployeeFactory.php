<?php

namespace Database\Factories;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'department_id' => null,
            'last_name' => fake()->lastName(),
            'first_name' => fake()->firstName(),
            'middle_name' => null,
            'position' => fake()->jobTitle(),
            'personnel_number' => fake()->unique()->numerify('######'),
            'phone' => '+99890'.fake()->numerify('#######'),
            'status' => EmployeeStatus::Active,
            'hired_at' => fake()->dateTimeBetween('-10 years', '-1 month')->format('Y-m-d'),
        ];
    }

    public function dismissed(): static
    {
        return $this->state(fn () => [
            'status' => EmployeeStatus::Dismissed,
            'dismissed_at' => now()->subDay()->toDateString(),
        ]);
    }
}
