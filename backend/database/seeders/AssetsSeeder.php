<?php

namespace Database\Seeders;

use App\Models\AssetCategory;
use Illuminate\Database\Seeder;

/** Asset categories in every environment. Idempotent: existing rows (and admin edits) are kept. */
class AssetsSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array{code: string, name: string, name_ru: string, is_networked: bool}> $categories */
        $categories = config('assets.categories');
        foreach ($categories as $index => $category) {
            AssetCategory::firstOrCreate(['code' => $category['code']], [
                'name' => $category['name'],
                'name_ru' => $category['name_ru'],
                'is_networked' => $category['is_networked'],
                'sort_order' => ($index + 1) * 10,
            ]);
        }
    }
}
