<?php

namespace Database\Seeders;

use App\Models\ServiceProvider;
use Illuminate\Database\Seeder;

/** Mobile operators named in the requirements. Reference data: runs in every environment and never overwrites edits. */
class TelecomSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Uzmobile', 'Beeline', 'Ucell', 'Mobiuz'] as $name) {
            ServiceProvider::query()->firstOrCreate(['name' => $name], ['is_mobile_operator' => true, 'is_active' => true]);
        }
    }
}
