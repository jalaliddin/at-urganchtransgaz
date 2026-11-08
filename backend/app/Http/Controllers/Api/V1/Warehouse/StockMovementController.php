<?php

namespace App\Http\Controllers\Api\V1\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Resources\Warehouse\StockResource;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Warehouse\StockQueries;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** The movement journal of the user's warehouses. */
class StockMovementController extends Controller
{
    public function index(Request $request, StockQueries $queries): JsonResponse
    {
        Gate::authorize('warehouse.view');
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $query = StockMovement::query()->visibleTo($user)->with(['document', 'warehouse', 'material.unit', 'creator'])->latest('id');
        $queries->filterMovements($query, array_intersect_key((array) $request->input('filter', []), array_flip(StockQueries::MOVEMENT_FILTERS)));
        $page = $query->paginate(Pagination::perPage($request))->withQueryString();

        return ApiResponse::page($page, fn (StockMovement $m) => StockResource::movement($m));
    }
}
