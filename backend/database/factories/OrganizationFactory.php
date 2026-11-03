<?php

namespace Database\Factories;

use App\Enums\OrganizationType;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'type' => OrganizationType::Subsidiary,
            'name' => fake()->unique()->company(),
            'code' => 'ORG-'.fake()->unique()->numerify('#####'),
            'phone' => '+99862'.fake()->numerify('#######'),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function childOf(Organization $parent): static
    {
        return $this->state(fn () => ['parent_id' => $parent->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
