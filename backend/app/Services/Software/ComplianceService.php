<?php

namespace App\Services\Software;

use App\Enums\AntivirusEffective;
use App\Enums\AssetStatus;
use App\Models\AntivirusStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\SoftwareInstallation;
use App\Models\SoftwarePolicy;
use App\Models\User;
use App\Support\OrganizationSubtree;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Antivirus and required-software compliance of the user's devices (spec S4, S5). Devices are
 * assets of the device categories (and of categories named by a policy) that are still in use.
 */
class ComplianceService
{
    public const PROBLEMS = ['missing_required', 'unprotected', 'outdated'];

    /**
     * Per-device facts: effective antivirus status and missing required titles.
     *
     * @param  array<string, mixed>  $filters  organization
     * @return Collection<int, array{asset: Asset, antivirus: AntivirusEffective, product: string|null, missing: list<string>}>
     */
    public function devices(User $user, array $filters = []): Collection
    {
        $policies = SoftwarePolicy::query()->with('software')->get();
        $defaultCategories = AssetCategory::query()->whereIn('code', (array) config('software.device_categories'))->pluck('id')->all();
        $categories = array_values(array_unique([...$defaultCategories, ...$policies->pluck('asset_category_id')->filter()->all()]));

        $assets = $this->deviceQuery($user, $categories, $filters)->with('organization')->orderBy('inventory_number')->get();
        $statuses = AntivirusStatus::query()->with('software')->whereIn('asset_id', $assets->pluck('id'))->get()->keyBy('asset_id');
        $installed = SoftwareInstallation::query()->whereIn('asset_id', $assets->pluck('id'))->whereNotNull('software_id')
            ->get(['asset_id', 'software_id'])->groupBy('asset_id')->map(fn ($rows) => $rows->pluck('software_id')->all());
        $subtrees = $policies->pluck('organization_id')->filter()->unique()
            ->mapWithKeys(fn ($id) => [$id => OrganizationSubtree::ids((int) $id)]);

        return $assets->map(function (Asset $asset) use ($policies, $defaultCategories, $statuses, $installed, $subtrees): array {
            $status = $statuses->get($asset->id);
            $have = $installed->get($asset->id, []);
            $missing = $policies->filter(function (SoftwarePolicy $p) use ($asset, $defaultCategories, $subtrees): bool {
                $category = $p->asset_category_id === null ? in_array($asset->category_id, $defaultCategories, true) : $p->asset_category_id === $asset->category_id;
                $organization = $p->organization_id === null || in_array($asset->organization_id, $subtrees->get($p->organization_id, []), true);

                return $category && $organization;
            })->reject(fn (SoftwarePolicy $p) => in_array($p->software_id, $have, true))
                ->map(fn (SoftwarePolicy $p) => $p->software->name)->unique()->sort()->values()->all();

            return [
                'asset' => $asset,
                'antivirus' => AntivirusStatus::effective($status),
                'product' => $status === null ? null : ($status->software->name ?? $status->product_name),
                'missing' => array_values($missing),
            ];
        });
    }

    /**
     * Counts per organization of the devices.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function summary(User $user, array $filters = []): array
    {
        return $this->devices($user, $filters)->groupBy(fn (array $d) => $d['asset']->organization_id)
            ->map(function (Collection $devices): array {
                $first = $devices->first();
                $av = array_fill_keys(array_map(fn (AntivirusEffective $e) => $e->value, AntivirusEffective::cases()), 0);
                foreach ($devices as $d) {
                    $av[$d['antivirus']->value]++;
                }

                return [
                    'organization' => ['id' => $first['asset']->organization->id, 'name' => $first['asset']->organization->name],
                    'devices' => $devices->count(),
                    'antivirus' => $av,
                    'protected' => $devices->filter(fn (array $d) => $d['antivirus']->isProtected())->count(),
                    'missing_required' => $devices->filter(fn (array $d) => $d['missing'] !== [])->count(),
                    'inventoried' => $devices->filter(fn (array $d) => $d['asset']->software_inventoried_at !== null)->count(),
                ];
            })->sortBy('organization.name')->values()->all();
    }

    /** @param  array{antivirus: AntivirusEffective, missing: list<string>}  $device */
    public static function hasProblem(array $device, string $problem): bool
    {
        return match ($problem) {
            'missing_required' => $device['missing'] !== [],
            'unprotected' => ! $device['antivirus']->isProtected(),
            'outdated' => $device['antivirus'] === AntivirusEffective::Outdated,
            default => true,
        };
    }

    /**
     * @param  list<int>  $categories
     * @param  array<string, mixed>  $filters
     * @return Builder<Asset>
     */
    private function deviceQuery(User $user, array $categories, array $filters): Builder
    {
        $query = Asset::query()->visibleTo($user)->whereIn('category_id', $categories ?: [0])
            ->whereNotIn('status', [AssetStatus::WrittenOff->value, AssetStatus::Lost->value]);
        if (! empty($filters['organization'])) {
            $query->whereIn('assets.organization_id', OrganizationSubtree::ids((int) $filters['organization']) ?: [0]);
        }

        return $query;
    }
}
