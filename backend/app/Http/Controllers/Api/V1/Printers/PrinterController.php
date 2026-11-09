<?php

namespace App\Http\Controllers\Api\V1\Printers;

use App\Enums\PrinterConnection;
use App\Http\Controllers\Controller;
use App\Http\Resources\Printers\PrinterResource;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\CartridgeModel;
use App\Models\PrinterCounter;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Printers\PrinterCounters;
use App\Services\Printers\PrinterPages;
use App\Services\Printers\PrinterRegistry;
use App\Support\ApiResponse;
use App\Support\Decimal;
use App\Support\Pagination;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Printer registry, printer page, profile and counter readings (design P1, P7, P11). */
class PrinterController extends Controller
{
    private const RELATIONS = ['organization', 'model.manufacturer', 'location', 'holder', 'printer'];

    public function __construct(
        private readonly PrinterRegistry $registry,
        private readonly PrinterCounters $counters,
        private readonly PrinterPages $pages,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('printers.view');
        $query = $this->registry->filtered($this->user($request), [
            ...(array) $request->input('filter', []), 'search' => $request->input('search'),
        ])->with(self::RELATIONS);

        $page = $query->paginate(Pagination::perPage($request))->withQueryString();
        $ids = $page->getCollection()->map(fn (Asset $a) => $a->id)->all();
        [$counters, $replacements] = [$this->registry->lastCounters($ids), $this->registry->lastReplacements($ids)];

        return ApiResponse::page($page, fn (Asset $a) => PrinterResource::printer($a, $counters[$a->id] ?? null, $replacements[$a->id] ?? null));
    }

    public function show(Request $request, int $printer): JsonResponse
    {
        Gate::authorize('printers.view');
        $user = $this->user($request);

        return $this->detail($this->registry->find($user, $printer), $user);
    }

    public function update(Request $request, int $printer): JsonResponse
    {
        Gate::authorize('printers.service');
        $user = $this->user($request);
        $asset = $this->registry->find($user, $printer);
        $this->registry->updateProfile($asset, $request->validate([
            'connection_type' => ['nullable', Rule::enum(PrinterConnection::class)],
            'is_color' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]));

        return $this->detail($asset->refresh(), $user);
    }

    public function counters(Request $request, int $printer): JsonResponse
    {
        Gate::authorize('printers.view');
        $user = $this->user($request);
        $asset = $this->registry->find($user, $printer);
        $page = PrinterCounter::query()->withoutGlobalScopes()->with('creator')
            ->joinSub($this->pages->readings([$asset->id])->select(['id', 'pages']), 'rp', 'rp.id', '=', 'printer_counters.id')
            ->where('printer_counters.asset_id', $asset->id)->select(['printer_counters.*', 'rp.pages'])
            ->orderByDesc('printer_counters.read_at')->orderByDesc('printer_counters.id')
            ->paginate(Pagination::perPage($request))->withQueryString();
        $editable = $this->editable($asset, $user);

        return ApiResponse::page($page, fn (PrinterCounter $c) => $this->counterRow($c, $editable));
    }

    public function storeCounter(Request $request, int $printer): JsonResponse
    {
        Gate::authorize('printers.service');
        $user = $this->user($request);
        $asset = $this->registry->find($user, $printer);
        $counter = $this->counters->record($asset, $request->validate(self::counterRules()), $user);
        $pages = $this->pages->readings([$asset->id])->where('id', $counter->id)->value('pages');
        $counter->setAttribute('pages', $pages)->load('creator');

        return ApiResponse::data($this->counterRow($counter, true), 201);
    }

    public function destroyCounter(Request $request, int $printerCounter): JsonResponse
    {
        Gate::authorize('printers.service');
        $user = $this->user($request);
        $counter = PrinterCounter::query()->withoutGlobalScopes()->findOrFail($printerCounter);
        $this->registry->find($user, $counter->asset_id);
        $this->counters->delete($counter);

        return ApiResponse::message(__('messages.deleted'));
    }

    /** @return array<string, mixed> */
    public static function counterRules(): array
    {
        return [
            'read_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.CarbonImmutable::today((string) config('app.display_timezone'))->toDateString()],
            'total' => ['required', 'integer', 'min:0', 'max:99999999999'],
            'is_reset' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function detail(Asset $asset, User $user): JsonResponse
    {
        $asset->load([...self::RELATIONS, 'department']);
        $defined = $asset->model_id !== null
            && DB::table('printer_cartridge_compatibilities')->where('asset_model_id', $asset->model_id)->exists();
        $cartridges = CartridgeModel::query()->with('material.unit')->where('is_active', true)
            ->when($defined, fn ($q) => $q->whereHas('printerModels', fn ($m) => $m->whereKey($asset->model_id)))
            ->orderBy('name')->get();
        $stock = $this->stockByWarehouse($user, $cartridges->map(fn (CartridgeModel $c) => $c->material_id)->all());
        $today = CarbonImmutable::today((string) config('app.display_timezone'));

        return ApiResponse::data([
            ...PrinterResource::printer($asset, $this->registry->lastCounters([$asset->id])[$asset->id] ?? null,
                $this->registry->lastReplacements([$asset->id])[$asset->id] ?? null),
            'department' => $asset->department === null ? null : ['id' => $asset->department->id, 'name' => $asset->department->name],
            'compatibility_defined' => $defined,
            'cartridges' => $cartridges->map(function (CartridgeModel $c) use ($stock): array {
                $warehouses = $stock[$c->material_id] ?? [];
                $total = array_reduce($warehouses, fn (string $sum, array $w) => Decimal::add($sum, $w['quantity']), '0.000');

                return [...PrinterResource::cartridge($c, $total), 'warehouses' => $warehouses];
            })->values(),
            'monthly_pages' => $this->pages->monthly($asset, $today),
            'maintenances' => AssetMaintenance::query()->with('ticket')->where('asset_id', $asset->id)
                ->latest('started_at')->limit(20)->get()->map(fn (AssetMaintenance $m) => PrinterResource::maintenance($m))->values(),
            'can' => [
                'service' => $this->editable($asset, $user),
                'issue_from_warehouse' => $user->can('warehouse.issue'),
                'view_asset' => $user->can('assets.view'),
            ],
        ]);
    }

    private function editable(Asset $asset, User $user): bool
    {
        return $user->can('printers.service') && ! $asset->status->isTerminal() && $asset->transfer_id === null;
    }

    /**
     * Stock of the materials per active warehouse the user can see.
     *
     * @param  list<int>  $materialIds
     * @return array<int, list<array{warehouse: array{id: int, name: string, organization: string}, quantity: string}>>
     */
    private function stockByWarehouse(User $user, array $materialIds): array
    {
        if ($materialIds === []) {
            return [];
        }
        $rows = StockBalance::query()->visibleTo($user)->whereIn('material_id', $materialIds)->where('quantity', '>', 0)
            ->groupBy('material_id', 'warehouse_id')->selectRaw('material_id, warehouse_id, SUM(quantity) AS qty')->get();
        $warehouses = Warehouse::query()->withoutGlobalScopes()->with('organization')->where('is_active', true)
            ->whereIn('id', $rows->pluck('warehouse_id')->unique()->all())->orderBy('name')->get()->keyBy('id');

        $result = [];
        foreach ($warehouses as $warehouse) {
            foreach ($rows->where('warehouse_id', $warehouse->id) as $row) {
                $result[(int) $row->getAttribute('material_id')][] = [
                    'warehouse' => ['id' => $warehouse->id, 'name' => $warehouse->name, 'organization' => $warehouse->organization->name],
                    'quantity' => Decimal::of((string) $row->getAttribute('qty')),
                ];
            }
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function counterRow(PrinterCounter $c, bool $editable): array
    {
        $pages = $c->getAttribute('pages');

        return [
            'id' => $c->id,
            'read_at' => $c->read_at->toDateString(),
            'total' => $c->total,
            'is_reset' => $c->is_reset,
            'pages' => $pages === null ? null : (int) $pages,
            'source' => $c->source->value,
            'replacement_id' => $c->replacement_id,
            'note' => $c->note,
            'created_by' => PrinterResource::user($c->creator),
            'created_at' => $c->created_at->toIso8601String(),
            'can_delete' => $editable,
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
