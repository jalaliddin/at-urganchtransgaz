<?php

namespace App\Services\Exports;

use App\Http\Controllers\Api\V1\Assets\AssetController;
use App\Models\Asset;
use App\Models\User;
use App\Services\Assets\AssetFilters;
use App\Support\Search\SearchTerm;

/** Asset register export (same filters and scope as the list screen). */
class AssetReport implements ReportDefinition
{
    public function permission(): string
    {
        return 'assets.export';
    }

    public function filterNames(): array
    {
        return [...AssetFilters::NAMES, 'search'];
    }

    public function title(string $locale): string
    {
        return __('exports.assets.title', [], $locale);
    }

    public function columns(string $locale): array
    {
        return array_map(
            fn (string $key): string => __("exports.assets.columns.{$key}", [], $locale),
            ['inventory_number', 'name', 'category', 'model', 'serial_number', 'hostname', 'ip_address', 'mac_address',
                'organization', 'location', 'holder', 'responsible', 'status', 'purchase_date', 'warranty_until', 'cost'],
        );
    }

    public function rows(User $user, array $filters, string $locale): iterable
    {
        $query = Asset::query()->visibleTo($user)
            ->with(['category', 'manufacturer', 'model', 'organization', 'location.parent', 'holder', 'responsible']);
        AssetFilters::applyAll($query, $filters);
        if (isset($filters['search']) && is_string($filters['search'])) {
            SearchTerm::whereContains($query, AssetController::SEARCH_COLUMNS, SearchTerm::normalize($filters['search']));
        }

        foreach ($query->lazyById(500, 'assets.id', 'id') as $asset) {
            /** @var Asset $asset */
            yield [
                $asset->inventory_number,
                $asset->name,
                $asset->category?->localizedName($locale),
                trim(($asset->manufacturer->name ?? '').' '.($asset->model->name ?? '')) ?: null,
                $asset->serial_number,
                $asset->hostname,
                $asset->ip_address,
                $asset->mac_address,
                $asset->organization?->name,
                $asset->location ? $asset->location->fullName().($asset->room ? ', '.$asset->room : '') : $asset->room,
                $asset->holder?->full_name,
                $asset->responsible?->full_name,
                $asset->status->label($locale),
                $asset->purchase_date?->format('d.m.Y'),
                $asset->warranty_until?->format('d.m.Y'),
                $asset->cost === null ? null : (float) $asset->cost,
            ];
        }
    }
}
