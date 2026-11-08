<?php

namespace Database\Seeders;

use App\Models\MaterialCategory;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/** Units and material categories in every environment. Idempotent: admin edits are kept. */
class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array{code: string, name: string, name_ru: string}> $units */
        $units = config('warehouse.units');
        foreach ($units as $index => $unit) {
            Unit::firstOrCreate(['code' => $unit['code']], [
                'name' => $unit['name'], 'name_ru' => $unit['name_ru'], 'sort_order' => ($index + 1) * 10,
            ]);
        }

        /** @var list<array{code: string, name: string, name_ru: string}> $categories */
        $categories = config('warehouse.categories');
        foreach ($categories as $index => $category) {
            MaterialCategory::firstOrCreate(['code' => $category['code']], [
                'name' => $category['name'], 'name_ru' => $category['name_ru'], 'sort_order' => ($index + 1) * 10,
            ]);
        }
    }
}
