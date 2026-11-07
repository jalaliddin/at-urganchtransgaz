<?php

namespace App\Http\Controllers\Api\V1\Assets;

use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Assets\CatalogResource;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetModel;
use App\Models\Manufacturer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Asset categories, manufacturers and models (global reference data). */
class AssetCatalogController extends Controller
{
    public function categories(Request $request): JsonResponse
    {
        Gate::authorize('assets.use');
        $rows = AssetCategory::query()
            ->when($request->boolean('filter.active'), fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')->orderBy('name')->get();

        return ApiResponse::data($rows->map(fn ($c) => CatalogResource::category($c))->values());
    }

    public function storeCategory(Request $request): JsonResponse
    {
        Gate::authorize('assets.manage_catalog');
        $category = AssetCategory::create($request->validate($this->categoryRules(true)));

        return ApiResponse::data(CatalogResource::category($category->refresh()), 201);
    }

    public function updateCategory(Request $request, AssetCategory $assetCategory): JsonResponse
    {
        Gate::authorize('assets.manage_catalog');
        $assetCategory->update($request->validate($this->categoryRules(false)));

        return ApiResponse::data(CatalogResource::category($assetCategory));
    }

    public function destroyCategory(AssetCategory $assetCategory): JsonResponse
    {
        Gate::authorize('assets.manage_catalog');
        $inUse = AssetModel::query()->where('category_id', $assetCategory->id)->exists()
            || Asset::query()->withoutGlobalScopes()->where('category_id', $assetCategory->id)->exists();
        $this->guard($inUse);
        $assetCategory->delete();

        return ApiResponse::message(__('messages.deleted'));
    }

    public function manufacturers(): JsonResponse
    {
        Gate::authorize('assets.use');

        return ApiResponse::data(Manufacturer::query()->orderBy('name')->get()->map(fn ($m) => CatalogResource::manufacturer($m))->values());
    }

    public function storeManufacturer(Request $request): JsonResponse
    {
        Gate::authorize('assets.manage_catalog');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('manufacturers', 'name')],
            'website' => ['nullable', 'url', 'max:255'],
        ]);

        return ApiResponse::data(CatalogResource::manufacturer(Manufacturer::create($data)), 201);
    }

    public function updateManufacturer(Request $request, Manufacturer $manufacturer): JsonResponse
    {
        Gate::authorize('assets.manage_catalog');
        $manufacturer->update($request->validate([
            'name' => ['sometimes', 'string', 'max:150', Rule::unique('manufacturers', 'name')->ignore($manufacturer->id)],
            'website' => ['nullable', 'url', 'max:255'],
        ]));

        return ApiResponse::data(CatalogResource::manufacturer($manufacturer));
    }

    public function destroyManufacturer(Manufacturer $manufacturer): JsonResponse
    {
        Gate::authorize('assets.manage_catalog');
        $this->guard($manufacturer->models()->exists()
            || Asset::query()->withoutGlobalScopes()->where('manufacturer_id', $manufacturer->id)->exists());
        $manufacturer->delete();

        return ApiResponse::message(__('messages.deleted'));
    }

    public function models(Request $request): JsonResponse
    {
        Gate::authorize('assets.use');
        $rows = AssetModel::query()->with(['manufacturer', 'category'])
            ->when($request->filled('filter.manufacturer'), fn ($q) => $q->where('manufacturer_id', $request->integer('filter.manufacturer')))
            ->when($request->filled('filter.category'), fn ($q) => $q->where('category_id', $request->integer('filter.category')))
            ->orderBy('name')->get();

        return ApiResponse::data($rows->map(fn ($m) => CatalogResource::model($m))->values());
    }

    public function storeModel(Request $request): JsonResponse
    {
        Gate::authorize('assets.manage_catalog');
        $model = AssetModel::create($request->validate($this->modelRules(true, null)));

        return ApiResponse::data(CatalogResource::model($model->load(['manufacturer', 'category'])), 201);
    }

    public function updateModel(Request $request, AssetModel $assetModel): JsonResponse
    {
        Gate::authorize('assets.manage_catalog');
        $assetModel->update($request->validate($this->modelRules(false, $assetModel)));

        return ApiResponse::data(CatalogResource::model($assetModel->load(['manufacturer', 'category'])));
    }

    public function destroyModel(AssetModel $assetModel): JsonResponse
    {
        Gate::authorize('assets.manage_catalog');
        $this->guard(Asset::query()->withoutGlobalScopes()->where('model_id', $assetModel->id)->exists());
        $assetModel->delete();

        return ApiResponse::message(__('messages.deleted'));
    }

    /** @return array<string, mixed> */
    private function categoryRules(bool $creating): array
    {
        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:150'],
            'name_ru' => ['nullable', 'string', 'max:150'],
            'is_networked' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }

    /** @return array<string, mixed> */
    private function modelRules(bool $creating, ?AssetModel $model): array
    {
        $req = $creating ? 'required' : 'sometimes';
        $manufacturerId = request()->input('manufacturer_id', $model?->manufacturer_id);

        return [
            'manufacturer_id' => [$req, 'integer', Rule::exists('manufacturers', 'id')],
            'category_id' => [$req, 'integer', Rule::exists('asset_categories', 'id')],
            'name' => [$req, 'string', 'max:150', Rule::unique('asset_models', 'name')
                ->where('manufacturer_id', $manufacturerId)->ignore($model?->id)],
            'specs' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function guard(bool $inUse): void
    {
        if ($inUse) {
            throw new DomainConflictException(__('messages.catalog_in_use'));
        }
    }
}
