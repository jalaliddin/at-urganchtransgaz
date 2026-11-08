<?php

namespace App\Http\Controllers\Api\V1\Warehouse;

use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Warehouse\WarehouseCatalogResource;
use App\Models\Material;
use App\Models\RequisitionItem;
use App\Models\StockBalance;
use App\Models\StockDocumentLine;
use App\Services\Warehouse\MaterialCatalog;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MaterialController extends Controller
{
    public function __construct(private readonly MaterialCatalog $catalog) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('warehouse.catalog');
        $search = trim((string) $request->input('filter.search', ''));
        $query = Material::query()->with(['category', 'unit'])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->when($request->filled('filter.category'), fn ($q) => $q->where('category_id', $request->integer('filter.category')))
            ->when($request->boolean('filter.active'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')->orderBy('id');
        $page = $query->paginate(Pagination::perPage($request))->withQueryString();

        return ApiResponse::page($page, fn (Material $m) => WarehouseCatalogResource::material($m));
    }

    public function show(Material $material): JsonResponse
    {
        Gate::authorize('warehouse.catalog');

        return ApiResponse::data(WarehouseCatalogResource::material($material->load(['category', 'unit'])));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('warehouse.manage');
        $material = $this->catalog->create($request->validate($this->rules(null)));

        return ApiResponse::data(WarehouseCatalogResource::material($material->refresh()->load(['category', 'unit'])), 201);
    }

    public function update(Request $request, Material $material): JsonResponse
    {
        Gate::authorize('warehouse.manage');
        $this->catalog->update($material, $request->validate($this->rules($material)));

        return ApiResponse::data(WarehouseCatalogResource::material($material->refresh()->load(['category', 'unit'])));
    }

    public function destroy(Material $material): JsonResponse
    {
        Gate::authorize('warehouse.manage');
        if (StockBalance::query()->where('material_id', $material->id)->exists()
            || StockDocumentLine::query()->where('material_id', $material->id)->exists()
            || RequisitionItem::query()->where('material_id', $material->id)->exists()) {
            throw new DomainConflictException(__('messages.warehouse_catalog_in_use'));
        }
        $material->delete();

        return ApiResponse::message(__('messages.deleted'));
    }

    /** @return array<string, mixed> */
    private function rules(?Material $material): array
    {
        $req = $material === null ? 'required' : 'sometimes';

        return [
            'code' => [$material === null ? 'nullable' : 'sometimes', 'string', 'max:50', Rule::unique('materials', 'code')->ignore($material?->id)],
            'name' => [$req, 'string', 'max:255'],
            'category_id' => [$req, 'integer', Rule::exists('material_categories', 'id')],
            'unit_id' => [$req, 'integer', Rule::exists('units', 'id')],
            'track_batches' => ['sometimes', 'boolean'],
            'track_expiry' => ['sometimes', 'boolean'],
            'min_quantity' => ['sometimes', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,3'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
