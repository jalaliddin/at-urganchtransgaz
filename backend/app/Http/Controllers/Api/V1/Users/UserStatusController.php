<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Users\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserStatusController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function deactivate(Request $request, User $user): JsonResponse
    {
        Gate::authorize('deactivate', $user);
        $this->users->deactivate($user, $request->user());

        return ApiResponse::data(new UserResource($user->refresh()->load(UserResource::RELATIONS)));
    }

    public function activate(Request $request, User $user): JsonResponse
    {
        Gate::authorize('deactivate', $user);
        $this->users->activate($user, $request->user());

        return ApiResponse::data(new UserResource($user->refresh()->load(UserResource::RELATIONS)));
    }
}
