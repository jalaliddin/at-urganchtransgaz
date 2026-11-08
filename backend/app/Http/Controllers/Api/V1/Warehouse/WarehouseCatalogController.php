<?php

namespace App\Http\Controllers\Api\V1\Warehouse;

use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Warehouse\WarehouseCatalogResource;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\RequisitionItem;
use App\Models\Unit;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Units of measure and material categories (global reference data). */
class WarehouseCatalogController extends Controller
{
    public function units(Request $request): JsonResponse
    {
        Gate::authorize('warehouse.catalog');
        $rows = Unit::query()->when($request->boolean('filter.active'), fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')->orderBy('name')->get();

        return ApiResponse::data($rows->map(fn (Unit $u) => WarehouseCatalogResource::unit($u))->values());
    }

    public function storeUnit(Request $request): JsonResponse
    {
        Gate::authorize('warehouse.manage');
        $unit = Unit::create($request->validate($this->unitRules(null)));

        return ApiResponse::data(WarehouseCatalogResource::unit($unit->refresh()), 201);
    }

    public function updateUnit(Request $request, Unit $unit): JsonResponse
    {
        Gate::authorize('warehouse.manage');
        $unit->update($request->validate($this->unitRules($unit)));

        return ApiResponse::data(WarehouseCatalogResource::unit($unit));
    }

    public function destroyUnit(Unit $unit): JsonResponse
    {
        Gate::authorize('warehouse.manage');
        $this->guard(Material::query()->where('unit_id', $unit->id)->exists()
            || RequisitionItem::query()->where('unit_id', $unit->id)->exists());
        $unit->delete();

        return ApiResponse::message(__('messages.deleted'));
    }

    public function categories(Request $request): JsonResponse
    {
        Gate::authorize('warehouse.catalog');
        $rows = MaterialCategory::query()->when($request->boolean('filter.active'), fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')->orderBy('name')->get();

        return ApiResponse::data($rows->map(fn (MaterialCategory $c) => WarehouseCatalogResource::category($c))->values());
    }

    public function storeCategory(Request $request): JsonResponse
    {
        Gate::authorize('warehouse.manage');
        $category = MaterialCategory::create($request->validate($this->categoryRules(true)));

        return ApiResponse::data(WarehouseCatalogResource::category($category->refresh()), 201);
    }

    public function updateCategory(Request $request, MaterialCategory $materialCategory): JsonResponse
    {
        Gate::authorize('warehouse.manage');
        $materialCategory->update($request->validate($this->categoryRules(false)));

        return ApiResponse::data(WarehouseCatalogResource::category($materialCategory));
    }

    public function destroyCategory(MaterialCategory $materialCategory): JsonResponse
    {
        Gate::authorize('warehouse.manage');
        $this->guard($materialCategory->materials()->exists());
        $materialCategory->delete();

        return ApiResponse::message(__('messages.deleted'));
    }

    /** @return array<string, mixed> */
    private function unitRules(?Unit $unit): array
    {
        $req = $unit === null ? 'required' : 'sometimes';

        return [
            'code' => [$req, 'string', 'max:30', 'regex:/^[a-z0-9_.-]+$/i', Rule::unique('units', 'code')->ignore($unit?->id)],
            'name' => [$req, 'string', 'max:50'],
            'name_ru' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }

    /** @return array<string, mixed> */
    private function categoryRules(bool $creating): array
    {
        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:150'],
            'name_ru' => ['nullable', 'string', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }

    private function guard(bool $inUse): void
    {
        if ($inUse) {
            throw new DomainConflictException(__('messages.warehouse_catalog_in_use'));
        }
    }
}
