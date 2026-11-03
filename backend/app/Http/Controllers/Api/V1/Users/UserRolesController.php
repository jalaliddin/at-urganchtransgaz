<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\SyncRolesRequest;
use App\Http\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Users\AccessManager;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class UserRolesController extends Controller
{
    public function __invoke(SyncRolesRequest $request, User $user, AccessManager $access): JsonResponse
    {
        /** @var list<string> $roles */
        $roles = $request->validated('roles');
        $access->syncRoles($user, $roles, $request->user());

        return ApiResponse::data(new UserResource($user->refresh()->load(UserResource::RELATIONS)));
    }
}
