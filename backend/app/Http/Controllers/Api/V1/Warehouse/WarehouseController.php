<?php

namespace App\Http\Controllers\Api\V1\Warehouse;

use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Warehouse\WarehouseCatalogResource;
use App\Models\Location;
use App\Models\StockCount;
use App\Models\StockDocument;
use App\Models\User;
use App\Models\Warehouse;
use App\Rules\AccessibleOrganization;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Warehouses of the organizations in the user's scope (the global scope applies). */
class WarehouseController extends Controller
{
    private const RELATIONS = ['organization', 'location.parent', 'responsible'];

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('warehouse.catalog');
        $query = Warehouse::query()->with(self::RELATIONS)->orderBy('name')
            ->when($request->boolean('filter.active'), fn ($q) => $q->where('is_active', true));
        if ($request->filled('filter.organization')) {
            $query->withinOrganizationSubtree($request->integer('filter.organization'));
        }

        return ApiResponse::data($query->get()->map(fn (Warehouse $w) => WarehouseCatalogResource::warehouse($w))->values());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('warehouse.manage');
        $organizationId = $request->integer('organization_id');
        $data = $request->validate([
            'organization_id' => ['required', 'integer', new AccessibleOrganization],
            ...$this->rules($organizationId, null),
        ]);
        $this->checkLocation($data, $organizationId);
        $warehouse = Warehouse::create($data);

        return ApiResponse::data(WarehouseCatalogResource::warehouse($warehouse->load(self::RELATIONS)), 201);
    }

    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        Gate::authorize('warehouse.manage');
        $data = $request->validate($this->rules($warehouse->organization_id, $warehouse));
        $this->checkLocation($data, $warehouse->organization_id);
        $warehouse->update($data);

        return ApiResponse::data(WarehouseCatalogResource::warehouse($warehouse->load(self::RELATIONS)));
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        Gate::authorize('warehouse.manage');
        $used = $warehouse->balances()->exists()
            || StockDocument::query()->where('warehouse_id', $warehouse->id)->orWhere('target_warehouse_id', $warehouse->id)->exists()
            || StockCount::query()->withoutGlobalScopes()->where('warehouse_id', $warehouse->id)->exists();
        if ($used) {
            throw new DomainConflictException(__('messages.warehouse_catalog_in_use'));
        }
        $warehouse->delete();

        return ApiResponse::message(__('messages.deleted'));
    }

    /** @return array<string, mixed> */
    private function rules(int $organizationId, ?Warehouse $warehouse): array
    {
        $req = $warehouse === null ? 'required' : 'sometimes';

        return [
            'name' => [$req, 'string', 'max:150',
                Rule::unique('warehouses', 'name')->where('organization_id', $organizationId)->ignore($warehouse?->id)],
            'code' => ['nullable', 'string', 'max:50'],
            'location_id' => ['nullable', 'integer'],
            'responsible_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The location must belong to the warehouse's organization; the responsible user
     * must be able to reach it.
     *
     * @param  array<string, mixed>  $data
     */
    private function checkLocation(array $data, int $organizationId): void
    {
        if (! empty($data['location_id'])) {
            $location = Location::query()->withoutGlobalScopes()->find($data['location_id']);
            if ($location === null || $location->organization_id !== $organizationId) {
                throw ValidationException::withMessages(['location_id' => __('messages.warehouse_location_invalid')]);
            }
        }
        if (! empty($data['responsible_user_id'])) {
            $user = User::query()->find($data['responsible_user_id']);
            if ($user === null || ! $user->canAccessOrganization($organizationId)) {
                throw ValidationException::withMessages(['responsible_user_id' => __('messages.warehouse_responsible_invalid')]);
            }
        }
    }
}
