<?php

namespace App\Http\Controllers\Api\V1\Warehouse;

use App\Enums\StockCountStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Warehouse\StockResource;
use App\Models\Material;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Warehouse\StockCountService;
use App\Support\ApiResponse;
use App\Support\Decimal;
use App\Support\Pagination;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Stock counts of warehouses in the user's organizations (global scope applies). */
class StockCountController extends Controller
{
    public function __construct(private readonly StockCountService $counts) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('warehouse.view');
        $query = StockCount::query()->with(['warehouse', 'starter'])->withCount([
            'lines', 'lines as counted_count' => fn ($q) => $q->whereNotNull('counted_quantity'),
        ])->latest('id');
        if ($request->filled('filter.warehouse')) {
            $query->where('warehouse_id', $request->integer('filter.warehouse'));
        }
        if ($request->filled('filter.status')) {
            $query->whereIn('status', explode(',', (string) $request->input('filter.status')));
        }
        $page = $query->paginate(Pagination::perPage($request))->withQueryString();

        return ApiResponse::page($page, fn (StockCount $c) => $this->row($c));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('warehouse.inventory');
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
                if (! Warehouse::query()->whereKey((int) $value)->exists()) {
                    $fail(__('messages.warehouse_not_accessible'));
                }
            }],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $count = $this->counts->start(Warehouse::query()->findOrFail($data['warehouse_id']), $data['note'] ?? null, $this->user($request));

        return $this->detail($count, 201);
    }

    public function show(StockCount $stockCount): JsonResponse
    {
        Gate::authorize('warehouse.view');

        return $this->detail($stockCount);
    }

    public function record(Request $request, StockCount $stockCount): JsonResponse
    {
        Gate::authorize('warehouse.inventory');
        $data = $request->validate([
            'lines' => ['present', 'array', 'max:1000'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.counted_quantity' => ['present', 'nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999999'],
        ]);
        /** @var list<array{id: int, counted_quantity: string|int|float|null}> $lines */
        $lines = array_values($data['lines']);
        $this->counts->record($stockCount, $lines, $this->user($request));

        return $this->detail($stockCount);
    }

    public function addLine(Request $request, StockCount $stockCount): JsonResponse
    {
        Gate::authorize('warehouse.inventory');
        $data = $request->validate([
            'material_id' => ['required', 'integer', 'exists:materials,id'],
            'batch' => ['nullable', 'string', 'max:100'],
            'expires_at' => ['nullable', 'date_format:Y-m-d'],
            'counted_quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999999'],
        ]);
        $this->counts->addLine($stockCount, Material::query()->findOrFail($data['material_id']), $data['batch'] ?? null,
            $data['expires_at'] ?? null, Decimal::of($data['counted_quantity']), $this->user($request));

        return $this->detail($stockCount, 201);
    }

    public function close(Request $request, StockCount $stockCount): JsonResponse
    {
        Gate::authorize('warehouse.inventory');

        return $this->detail($this->counts->close($stockCount, $this->user($request)));
    }

    public function cancel(Request $request, StockCount $stockCount): JsonResponse
    {
        Gate::authorize('warehouse.inventory');

        return $this->detail($this->counts->cancel($stockCount, $this->user($request)));
    }

    /** @return array<string, mixed> */
    private function row(StockCount $c): array
    {
        return [
            'id' => $c->id, 'number' => $c->number, 'status' => $c->status->value, 'note' => $c->note,
            'warehouse' => StockResource::warehouseRef($c->warehouse),
            'started_by' => $c->starter ? ['id' => $c->starter->id, 'name' => $c->starter->name] : null,
            'started_at' => $c->started_at?->toIso8601String(), 'closed_at' => $c->closed_at?->toIso8601String(),
            'progress' => ['total' => (int) $c->getAttribute('lines_count'), 'counted' => (int) $c->getAttribute('counted_count')],
        ];
    }

    private function detail(StockCount $count, int $status = 200): JsonResponse
    {
        $count->load(['warehouse', 'starter', 'closer', 'adjustment'])->loadCount([
            'lines', 'lines as counted_count' => fn ($q) => $q->whereNotNull('counted_quantity'),
        ]);
        $lines = StockCountLine::query()->with('material.unit')->where('count_id', $count->id)->orderBy('id')->get();

        return ApiResponse::data([
            ...$this->row($count),
            'closed_by' => $count->closer ? ['id' => $count->closer->id, 'name' => $count->closer->name] : null,
            'adjustment_document' => $count->adjustment ? ['id' => $count->adjustment->id, 'number' => $count->adjustment->number] : null,
            'lines' => $lines->map(fn (StockCountLine $l) => [
                'id' => $l->id, 'material' => StockResource::materialRef($l->material), 'batch' => $l->batch === '' ? null : $l->batch,
                'expires_at' => $l->expires_at?->toDateString(), 'expected_quantity' => $l->expected_quantity,
                'counted_quantity' => $l->counted_quantity,
                'difference' => $l->counted_quantity === null ? null : Decimal::sub($l->counted_quantity, $l->expected_quantity),
            ])->values(),
            'can' => ['manage' => $count->status === StockCountStatus::Open && request()->user()?->can('warehouse.inventory') === true],
        ], $status);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
