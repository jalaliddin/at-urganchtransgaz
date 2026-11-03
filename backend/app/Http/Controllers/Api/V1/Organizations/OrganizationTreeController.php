<?php

namespace App\Http\Controllers\Api\V1\Organizations;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Organizations\OrganizationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrganizationTreeController extends Controller
{
    public function __invoke(OrganizationService $organizations): JsonResponse
    {
        Gate::authorize('viewAny', Organization::class);

        return ApiResponse::data($organizations->tree());
    }
}
