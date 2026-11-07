<?php

namespace Tests\Concerns;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assets\AssetService;
use Database\Seeders\AssetsSeeder;

/** Asset fixtures. Requires InteractsWithAccess. */
trait CreatesAssets
{
    protected function seedAssets(): void
    {
        $this->seedRbac();
        $this->seed(AssetsSeeder::class);
    }

    protected function assetCategory(string $code = 'computer'): AssetCategory
    {
        return AssetCategory::where('code', $code)->firstOrFail();
    }

    /** @param  array<string, mixed>  $attributes */
    protected function assetIn(Organization $organization, User $by, array $attributes = []): Asset
    {
        return app(AssetService::class)->create([
            'organization_id' => $organization->id,
            'name' => 'HP ProDesk 400 G7',
            'category_id' => $this->assetCategory()->id,
            ...$attributes,
        ], $by);
    }
}
