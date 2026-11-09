<?php

namespace App\Http\Controllers\Api\V1\Printers;

use App\Enums\CartridgeColor;
use App\Enums\CartridgeKind;
use App\Http\Controllers\Controller;
use App\Http\Resources\Printers\PrinterResource;
use App\Models\AssetCategory;
use App\Models\AssetModel;
use App\Models\CartridgeModel;
use App\Models\CartridgeReplacement;
use App\Models\User;
use App\Services\Printers\CartridgeCatalog;
use App\Services\Printers\CartridgeStock;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Cartridge catalog and printer models (design P2, P3). */
class CartridgeModelController extends Controller
{
    public function __construct(private readonly CartridgeCatalog $catalog, private readonly CartridgeStock $stock) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('printers.view');
        $query = CartridgeModel::query()->with(['material.unit', 'printerModels.manufacturer'])->orderBy('name')->orderBy('id');
        if ($request->filled('filter.active')) {
            $query->where('is_active', $request->boolean('filter.active'));
        }
        if ($request->filled('filter.kind')) {
            $query->whereIn('kind', explode(',', (string) $request->input('filter.kind')));
        }
        if ($request->filled('filter.printer_model')) {
            $query->whereHas('printerModels', fn ($q) => $q->whereKey($request->integer('filter.printer_model')));
        }
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('part_number', 'like', "%{$search}%")
                ->orWhereHas('material', fn ($m) => $m->where('code', 'like', "%{$search}%")));
        }
        $page = $query->paginate(Pagination::perPage($request))->withQueryString();
        /** @var list<CartridgeModel> $items */
        $items = $page->getCollection()->all();
        $stock = $this->stock->onHand($this->user($request), array_map(fn (CartridgeModel $c) => $c->material_id, $items));
        $used = CartridgeReplacement::query()->withoutGlobalScopes()->whereIn('cartridge_model_id', array_map(fn (CartridgeModel $c) => $c->id, $items))
            ->distinct()->pluck('cartridge_model_id')->map(fn ($id) => (int) $id)->all();

        return ApiResponse::page($page, fn (CartridgeModel $c) => PrinterResource::cartridge($c, $stock[$c->material_id] ?? '0.000', ! in_array($c->id, $used, true)));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('printers.manage');
        $cartridge = $this->catalog->save(null, $request->validate($this->rules(null)));

        return $this->show($request, $cartridge, 201);
    }

    public function update(Request $request, CartridgeModel $cartridgeModel): JsonResponse
    {
        Gate::authorize('printers.manage');
        $cartridge = $this->catalog->save($cartridgeModel, $request->validate($this->rules($cartridgeModel)));

        return $this->show($request, $cartridge);
    }

    public function destroy(CartridgeModel $cartridgeModel): JsonResponse
    {
        Gate::authorize('printers.manage');
        $this->catalog->delete($cartridgeModel);

        return ApiResponse::message(__('messages.deleted'));
    }

    /** Printer models = asset models of the printer category. */
    public function printerModels(): JsonResponse
    {
        Gate::authorize('printers.view');
        $models = AssetModel::query()->with('manufacturer')->whereIn('id', CartridgeCatalog::printerModelIds())->orderBy('name')->get();

        return ApiResponse::data($models->map(fn (AssetModel $m) => PrinterResource::printerModel($m))->values());
    }

    private function show(Request $request, CartridgeModel $cartridge, int $status = 200): JsonResponse
    {
        $cartridge->load(['material.unit', 'printerModels.manufacturer']);
        $stock = $this->stock->onHand($this->user($request), [$cartridge->material_id]);

        return ApiResponse::data(PrinterResource::cartridge($cartridge, $stock[$cartridge->material_id] ?? '0.000', ! $this->catalog->used($cartridge)), $status);
    }

    /** @return array<string, mixed> */
    private function rules(?CartridgeModel $cartridge): array
    {
        $printerCategory = AssetCategory::query()->where('code', AssetCategory::PRINTER)->value('id');

        return [
            'name' => ['required', 'string', 'max:150'],
            'part_number' => ['nullable', 'string', 'max:100', Rule::unique('cartridge_models', 'part_number')->ignore($cartridge?->id)],
            'kind' => ['required', Rule::enum(CartridgeKind::class)],
            'color' => ['required', Rule::enum(CartridgeColor::class)],
            'page_yield' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'is_active' => ['sometimes', 'boolean'],
            'material_id' => ['nullable', 'integer', Rule::exists('materials', 'id'),
                Rule::unique('cartridge_models', 'material_id')->ignore($cartridge?->id)],
            'printer_model_ids' => ['sometimes', 'array', 'max:200'],
            'printer_model_ids.*' => ['integer', 'distinct', Rule::exists('asset_models', 'id')->where('category_id', $printerCategory)],
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
