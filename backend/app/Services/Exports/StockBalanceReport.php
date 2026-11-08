<?php

namespace App\Services\Exports;

use App\Models\Material;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Warehouse\StockQueries;
use App\Support\Decimal;
use Carbon\CarbonImmutable;

/** Warehouse balances export (same filters and scope as the balances screen). */
class StockBalanceReport implements ReportDefinition
{
    public function __construct(private readonly StockQueries $queries) {}

    public function permission(): string
    {
        return 'warehouse.export';
    }

    public function filterNames(): array
    {
        return StockQueries::FILTERS;
    }

    public function title(string $locale): string
    {
        return __('exports.stock_balances.title', [], $locale);
    }

    public function columns(string $locale): array
    {
        return array_map(
            fn (string $key): string => __("exports.stock_balances.columns.{$key}", [], $locale),
            ['warehouse', 'organization', 'code', 'material', 'category', 'unit', 'quantity', 'min_quantity', 'value', 'nearest_expiry', 'low'],
        );
    }

    public function rows(User $user, array $filters, string $locale): iterable
    {
        /** @var array<int, Material|null> $materials */
        $materials = [];
        /** @var array<int, Warehouse|null> $warehouses */
        $warehouses = [];
        foreach ($this->queries->grouped($user, $filters)->cursor() as $row) {
            $material = $materials[$row->material_id] ??= Material::query()->with(['unit', 'category'])->find($row->material_id);
            $warehouse = $warehouses[$row->warehouse_id] ??= Warehouse::query()->withoutGlobalScopes()->with('organization')->find($row->warehouse_id);
            if ($material === null || $warehouse === null) {
                continue;
            }
            $quantity = Decimal::of((string) $row->quantity);
            $min = Decimal::of((string) $row->min_quantity);
            yield [
                $warehouse->name,
                $warehouse->organization->name,
                $material->code,
                $material->name,
                $material->category->localizedName($locale),
                $material->unit->localizedName($locale),
                (float) $quantity,
                (float) $min,
                (float) Decimal::of((string) $row->value, 2),
                $row->nearest_expiry ? CarbonImmutable::parse((string) $row->nearest_expiry)->format('d.m.Y') : null,
                Decimal::cmp($min, '0') > 0 && Decimal::cmp($quantity, $min) < 0 ? __('exports.yes', [], $locale) : null,
            ];
        }
    }
}
