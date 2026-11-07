<?php

namespace App\Services\Assets;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;

/** Asset list filters shared by the list endpoint and exports. */
final class AssetFilters
{
    public const NAMES = [
        'status', 'category', 'organization', 'department', 'location', 'holder', 'responsible', 'model',
        'manufacturer', 'warranty',
    ];

    /** @return list<AllowedFilter> */
    public static function allowed(User $user): array
    {
        return array_map(
            fn (string $name): AllowedFilter => AllowedFilter::callback($name, fn (Builder $q, mixed $v) => self::apply($q, $name, $v)),
            self::NAMES,
        );
    }

    /**
     * @param  Builder<Asset>  $query
     * @param  array<string, mixed>  $filters
     */
    public static function applyAll(Builder $query, array $filters): void
    {
        foreach (array_intersect_key($filters, array_flip(self::NAMES)) as $name => $value) {
            if ($value !== null && $value !== '' && $value !== []) {
                self::apply($query, (string) $name, $value);
            }
        }
    }

    /** @param  Builder<Asset>  $query */
    public static function apply(Builder $query, string $name, mixed $value): void
    {
        $values = array_values(array_filter(
            array_map(fn ($v) => trim((string) $v), is_array($value) ? $value : explode(',', (string) $value)),
            fn ($v) => $v !== '',
        ));
        $first = $values[0] ?? '';
        $ids = array_values(array_filter(array_map('intval', $values)));
        $column = fn (string $c): string => $query->qualifyColumn($c);

        match ($name) {
            'status' => self::in($query, $column('status'), array_values(array_filter($values, fn ($v) => AssetStatus::tryFrom($v) !== null))),
            'category' => self::in($query, $column('category_id'), $ids),
            'department' => self::in($query, $column('department_id'), $ids),
            'location' => self::in($query, $column('location_id'), $ids),
            'model' => self::in($query, $column('model_id'), $ids),
            'manufacturer' => self::in($query, $column('manufacturer_id'), $ids),
            'responsible' => self::in($query, $column('responsible_employee_id'), $ids),
            'holder' => $first === 'none' ? $query->whereNull($column('holder_employee_id')) : self::in($query, $column('holder_employee_id'), $ids),
            'organization' => ctype_digit($first) ? $query->withinOrganizationSubtree((int) $first) : $query->whereRaw('1 = 0'),
            'warranty' => self::warranty($query, $first),
            default => null,
        };
    }

    /**
     * @param  Builder<Asset>  $query
     * @param  array<int, int|string>  $values
     */
    private static function in(Builder $query, string $column, array $values): void
    {
        $values === [] ? $query->whereRaw('1 = 0') : $query->whereIn($column, $values);
    }

    /** @param  Builder<Asset>  $query */
    private static function warranty(Builder $query, string $value): void
    {
        $today = CarbonImmutable::now((string) config('app.display_timezone'))->toDateString();
        $column = $query->qualifyColumn('warranty_until');
        match ($value) {
            'expiring' => $query->whereBetween($column, [$today, CarbonImmutable::parse($today)->addDays((int) config('assets.warranty_notice_days'))->toDateString()]),
            'expired' => $query->where($column, '<', $today),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
