<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DashboardRequest;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(DashboardRequest $request, DashboardService $dashboard): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $organizationId = $request->filled('organization_id') ? $request->integer('organization_id') : null;

        return ApiResponse::data($dashboard->forUser($user, $organizationId));
    }
}
