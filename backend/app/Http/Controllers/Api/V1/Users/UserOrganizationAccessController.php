<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\SyncOrganizationAccessRequest;
use App\Http\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Users\AccessManager;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class UserOrganizationAccessController extends Controller
{
    public function __invoke(SyncOrganizationAccessRequest $request, User $user, AccessManager $access): JsonResponse
    {
        $access->syncOrganizationAccess($user, $request->rows(), $request->user());

        return ApiResponse::data(new UserResource($user->refresh()->load(UserResource::RELATIONS)));
    }
}
