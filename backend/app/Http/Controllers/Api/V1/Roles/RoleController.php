<?php

namespace App\Http\Controllers\Api\V1\Roles;

use App\Http\Controllers\Controller;
use App\Http\Requests\Roles\StoreRoleRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use App\Http\Resources\Roles\RoleResource;
use App\Models\Role;
use App\Services\Roles\RoleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Role::class);

        $roles = Role::query()->with('permissions')->withUsersCount()
            ->orderByDesc('is_system')->orderBy('id')->get();

        return ApiResponse::data(RoleResource::collection($roles)->resolve(request()));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        /** @var array{name: string, display_name: string, description?: string|null, permissions: list<string>} $data */
        $data = $request->validated();

        return ApiResponse::data(new RoleResource($this->roles->create($data)->load('permissions')), 201);
    }

    public function show(Role $role): JsonResponse
    {
        Gate::authorize('view', $role);

        $role = Role::query()->withUsersCount()->with('permissions')->findOrFail($role->id);

        return ApiResponse::data(new RoleResource($role));
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        /** @var array{display_name?: string, description?: string|null, permissions?: list<string>} $data */
        $data = $request->validated();

        return ApiResponse::data(new RoleResource($this->roles->update($role, $data)->load('permissions')));
    }

    public function destroy(Role $role): JsonResponse
    {
        Gate::authorize('delete', $role);
        $this->roles->delete($role);

        return ApiResponse::message(__('messages.deleted'));
    }
}
