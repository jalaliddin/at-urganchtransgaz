<?php

namespace App\Http\Controllers\Api\V1\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Resources\Warehouse\StockResource;
use App\Models\StockMovement;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** The movement journal of the user's warehouses (organization scope applies). */
class StockMovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('warehouse.view');
        $tz = (string) config('app.display_timezone');
        $query = StockMovement::query()->with(['document', 'warehouse', 'material.unit', 'creator'])->latest('id');

        if ($request->filled('filter.warehouse')) {
            $query->where('warehouse_id', $request->integer('filter.warehouse'));
        }
        if ($request->filled('filter.material')) {
            $query->where('material_id', $request->integer('filter.material'));
        }
        if ($request->filled('filter.document')) {
            $query->where('document_id', $request->integer('filter.document'));
        }
        if ($request->filled('filter.type')) {
            $types = explode(',', (string) $request->input('filter.type'));
            $query->whereHas('document', fn ($q) => $q->whereIn('type', $types));
        }
        if ($request->filled('filter.date_from')) {
            $query->where('created_at', '>=', CarbonImmutable::parse((string) $request->input('filter.date_from'), $tz)->startOfDay()->utc());
        }
        if ($request->filled('filter.date_to')) {
            $query->where('created_at', '<=', CarbonImmutable::parse((string) $request->input('filter.date_to'), $tz)->endOfDay()->utc());
        }
        $page = $query->paginate(Pagination::perPage($request))->withQueryString();

        return ApiResponse::page($page, fn (StockMovement $m) => StockResource::movement($m));
    }
}
