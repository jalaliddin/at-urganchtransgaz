<?php

namespace App\Services\Warehouse;

use App\Models\StockMovement;
use App\Models\User;
use App\Support\OrganizationSubtree;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read models over lots shared by the balances screen, the dashboard and exports.
 * Scope is applied from $user explicitly (exports run without an authenticated user).
 */
class StockQueries
{
    public const FILTERS = ['warehouse', 'material', 'category', 'organization', 'search', 'low', 'expiring'];

    public const MOVEMENT_FILTERS = ['warehouse', 'material', 'document', 'type', 'date_from', 'date_to'];

    /**
     * One row per warehouse and material: quantity, value, minimum, nearest expiry.
     * Without the low filter, rows with no stock and no minimum are left out.
     *
     * @param  array<string, mixed>  $filters
     */
    public function grouped(User $user, array $filters): Builder
    {
        $query = DB::table('stock_balances as b')
            ->join('materials as m', 'm.id', '=', 'b.material_id')
            ->join('warehouses as w', 'w.id', '=', 'b.warehouse_id')
            ->groupBy('b.warehouse_id', 'b.material_id', 'm.name', 'm.min_quantity')
            ->selectRaw('b.warehouse_id, b.material_id, m.min_quantity, SUM(b.quantity) AS quantity, SUM(b.quantity * b.unit_cost) AS value,
                MIN(CASE WHEN b.quantity > 0 THEN b.expires_at END) AS nearest_expiry')
            ->orderBy('m.name')->orderBy('b.warehouse_id');

        $this->scope($query, $user, 'w.organization_id');
        if (! empty($filters['warehouse'])) {
            $query->where('b.warehouse_id', (int) $filters['warehouse']);
        }
        if (! empty($filters['material'])) {
            $query->where('b.material_id', (int) $filters['material']);
        }
        if (! empty($filters['category'])) {
            $query->where('m.category_id', (int) $filters['category']);
        }
        if (! empty($filters['organization'])) {
            $query->whereIn('w.organization_id', OrganizationSubtree::ids((int) $filters['organization']) ?: [0]);
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('m.name', 'like', "%{$search}%")->orWhere('m.code', 'like', "%{$search}%"));
        }

        if (filter_var($filters['low'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->havingRaw('(m.min_quantity > 0 AND SUM(b.quantity) < m.min_quantity)');
        } else {
            $query->havingRaw('(SUM(b.quantity) > 0 OR m.min_quantity > 0)');
        }
        if (filter_var($filters['expiring'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->havingRaw('MIN(CASE WHEN b.quantity > 0 THEN b.expires_at END) <= ?', [$this->expiryLimit()]);
        }

        return $query;
    }

    /** Warehouse + material pairs below their minimum. */
    public function lowCount(User $user, ?int $organizationId = null): int
    {
        return DB::query()->fromSub($this->grouped($user, ['low' => true, 'organization' => $organizationId]), 'g')->count();
    }

    /** Lots with stock that expire within the notice period (or already expired). */
    public function expiringLots(User $user, ?int $organizationId = null): int
    {
        $query = DB::table('stock_balances as b')->join('warehouses as w', 'w.id', '=', 'b.warehouse_id')
            ->where('b.quantity', '>', 0)->whereNotNull('b.expires_at')->where('b.expires_at', '<=', $this->expiryLimit());
        $this->scope($query, $user, 'w.organization_id');
        if ($organizationId !== null) {
            $query->whereIn('w.organization_id', OrganizationSubtree::ids($organizationId) ?: [0]);
        }

        return $query->count();
    }

    /**
     * Journal filters shared by the screen and the export.
     *
     * @param  EloquentBuilder<StockMovement>  $query
     * @param  array<string, mixed>  $filters
     */
    public function filterMovements(EloquentBuilder $query, array $filters): void
    {
        $tz = (string) config('app.display_timezone');
        foreach (['warehouse' => 'warehouse_id', 'material' => 'material_id', 'document' => 'document_id'] as $name => $column) {
            if (! empty($filters[$name])) {
                $query->where($column, (int) $filters[$name]);
            }
        }
        if (! empty($filters['type'])) {
            $types = explode(',', (string) $filters['type']);
            $query->whereHas('document', fn ($q) => $q->whereIn('type', $types));
        }
        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', CarbonImmutable::parse((string) $filters['date_from'], $tz)->startOfDay()->utc());
        }
        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', CarbonImmutable::parse((string) $filters['date_to'], $tz)->endOfDay()->utc());
        }
    }

    public function expiryLimit(): string
    {
        return CarbonImmutable::now((string) config('app.display_timezone'))->addDays((int) config('warehouse.expiry_notice_days'))->toDateString();
    }

    private function scope(Builder $query, User $user, string $column): void
    {
        $scope = $user->accessScope();
        if (! $scope->isUnrestricted()) {
            $query->whereIn($column, $scope->organizationIds() ?: [0]);
        }
    }
}
