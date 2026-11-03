<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference data in every environment; demo data only in development/testing.
     * Both seeders are idempotent.
     */
    public function run(): void
    {
        $this->call(RbacSeeder::class);

        if ($this->container->environment(['local', 'development', 'testing'])) {
            $this->call(DemoSeeder::class);
        }
    }
}
