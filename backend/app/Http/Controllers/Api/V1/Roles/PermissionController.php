<?php

namespace App\Http\Controllers\Api\V1\Roles;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\ApiResponse;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PermissionController extends Controller
{
    /** Permission registry grouped by module (labels are translated by the SPA). */
    public function __invoke(PermissionRegistry $registry): JsonResponse
    {
        Gate::authorize('viewAny', Role::class);

        $modules = [];
        foreach ($registry->modules() as $module => $permissions) {
            $modules[] = ['module' => $module, 'permissions' => $permissions];
        }

        return ApiResponse::data($modules);
    }
}
