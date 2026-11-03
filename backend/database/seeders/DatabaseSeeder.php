<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Safe in every environment: only idempotent reference data.
     */
    public function run(): void
    {
        $this->call(RbacSeeder::class);
    }
}
