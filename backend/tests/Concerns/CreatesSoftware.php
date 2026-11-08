<?php

namespace Tests\Concerns;

use App\Models\Software;

/** Software fixtures. */
trait CreatesSoftware
{
    /** @param  array<string, mixed>  $attributes */
    protected function software(array $attributes = []): Software
    {
        $name = $attributes['name'] ?? 'Microsoft Office '.fake()->unique()->numberBetween(1, 99999);

        return Software::create([
            'name' => $name,
            'category' => 'office',
            'license_type' => 'commercial',
            ...$attributes,
        ]);
    }
}
