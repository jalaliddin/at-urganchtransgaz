<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationItResponsible;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationItResponsible>
 */
class OrganizationItResponsibleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'employee_id' => Employee::factory(),
            'is_primary' => false,
            'assigned_at' => now()->toDateString(),
        ];
    }
}
