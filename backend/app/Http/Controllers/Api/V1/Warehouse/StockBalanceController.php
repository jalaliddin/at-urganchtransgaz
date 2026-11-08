<?php

namespace App\Http\Controllers\Api\V1\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Resources\Warehouse\StockResource;
use App\Models\Material;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\ApiResponse;
use App\Support\Decimal;
use App\Support\OrganizationSubtree;
use App\Support\Pagination;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Balances per warehouse and material (with their lots) and live availability for the line editor. */
class StockBalanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('warehouse.view');
        $page = $this->grouped($request, $this->user($request))->paginate(Pagination::perPage($request))->withQueryString();

        /** @var Collection<int, \stdClass> $rows */
        $rows = collect($page->items());
        $materials = Material::query()->with(['unit', 'category'])->whereIn('id', $rows->pluck('material_id'))->get()->keyBy('id');
        $warehouses = Warehouse::query()->withoutGlobalScopes()->with('organization')->whereIn('id', $rows->pluck('warehouse_id'))->get()->keyBy('id');
        $lots = StockBalance::query()->whereIn('warehouse_id', $rows->pluck('warehouse_id'))->whereIn('material_id', $rows->pluck('material_id'))
            ->where('quantity', '>', 0)->orderByRaw('expires_at IS NULL')->orderBy('expires_at')->orderBy('received_at')->orderBy('id')->get()
            ->groupBy(fn (StockBalance $b) => $b->warehouse_id.':'.$b->material_id);

        $data = [];
        foreach ($rows as $row) {
            $material = $materials->get($row->material_id);
            $warehouse = $warehouses->get($row->warehouse_id);
            if ($material === null || $warehouse === null) {
                continue;
            }
            $quantity = Decimal::of((string) $row->quantity);
            $min = Decimal::of((string) $row->min_quantity);

            $data[] = [
                'id' => "{$row->warehouse_id}:{$row->material_id}",
                'warehouse' => ['id' => $warehouse->id, 'name' => $warehouse->name, 'organization' => ['id' => $warehouse->organization->id, 'name' => $warehouse->organization->name]],
                'material' => [...StockResource::materialRef($material), 'category' => $material->category->localizedName()],
                'quantity' => $quantity,
                'value' => Decimal::of((string) $row->value, 2),
                'min_quantity' => $min,
                'is_low' => Decimal::cmp($min, '0') > 0 && Decimal::cmp($quantity, $min) < 0,
                'nearest_expiry' => $row->nearest_expiry,
                'lots' => ($lots->get("{$row->warehouse_id}:{$row->material_id}") ?? collect())->map(fn ($b) => StockResource::lot($b))->values(),
            ];
        }

        return ApiResponse::data($data, meta: [
            'current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(),
            'last_page' => $page->lastPage(), 'from' => $page->firstItem(), 'to' => $page->lastItem(),
        ]);
    }

    /** Lots with stock for one warehouse and material, in the FIFO order an issue would take them. */
    public function availability(Request $request): JsonResponse
    {
        Gate::authorize('warehouse.view');
        $data = $request->validate(['warehouse_id' => ['required', 'integer'], 'material_id' => ['required', 'integer']]);
        if (! Warehouse::query()->whereKey($data['warehouse_id'])->exists()) {
            throw ValidationException::withMessages(['warehouse_id' => __('messages.warehouse_not_accessible')]);
        }
        $lots = StockBalance::query()->where('warehouse_id', $data['warehouse_id'])->where('material_id', $data['material_id'])
            ->where('quantity', '>', 0)->orderByRaw('expires_at IS NULL')->orderBy('expires_at')->orderBy('received_at')->orderBy('id')->get();
        $total = $lots->reduce(fn (string $sum, StockBalance $b) => Decimal::add($sum, $b->quantity), '0.000');

        return ApiResponse::data(['total' => $total, 'lots' => $lots->map(fn ($b) => StockResource::lot($b))->values()]);
    }

    private function grouped(Request $request, User $user): Builder
    {
        $query = DB::table('stock_balances as b')
            ->join('materials as m', 'm.id', '=', 'b.material_id')
            ->join('warehouses as w', 'w.id', '=', 'b.warehouse_id')
            ->groupBy('b.warehouse_id', 'b.material_id', 'm.name', 'm.min_quantity')
            ->selectRaw('b.warehouse_id, b.material_id, m.min_quantity, SUM(b.quantity) AS quantity, SUM(b.quantity * b.unit_cost) AS value,
                MIN(CASE WHEN b.quantity > 0 THEN b.expires_at END) AS nearest_expiry')
            ->orderBy('m.name')->orderBy('b.warehouse_id');

        $scope = $user->accessScope();
        if (! $scope->isUnrestricted()) {
            $query->whereIn('w.organization_id', $scope->organizationIds() ?: [0]);
        }
        if ($request->filled('filter.warehouse')) {
            $query->where('b.warehouse_id', $request->integer('filter.warehouse'));
        }
        if ($request->filled('filter.material')) {
            $query->where('b.material_id', $request->integer('filter.material'));
        }
        if ($request->filled('filter.category')) {
            $query->where('m.category_id', $request->integer('filter.category'));
        }
        if ($request->filled('filter.organization')) {
            $query->whereIn('w.organization_id', OrganizationSubtree::ids($request->integer('filter.organization')) ?: [0]);
        }
        $search = trim((string) $request->input('filter.search', ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('m.name', 'like', "%{$search}%")->orWhere('m.code', 'like', "%{$search}%"));
        }

        if ($request->boolean('filter.low')) {
            $query->havingRaw('(m.min_quantity > 0 AND SUM(b.quantity) < m.min_quantity)');
        } else {
            $query->havingRaw('(SUM(b.quantity) > 0 OR m.min_quantity > 0)');
        }
        if ($request->boolean('filter.expiring')) {
            $until = CarbonImmutable::now((string) config('app.display_timezone'))->addDays((int) config('warehouse.expiry_notice_days'))->toDateString();
            $query->havingRaw('MIN(CASE WHEN b.quantity > 0 THEN b.expires_at END) <= ?', [$until]);
        }

        return $query;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
