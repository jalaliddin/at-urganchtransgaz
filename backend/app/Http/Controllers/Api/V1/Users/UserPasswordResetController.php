<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Users\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserPasswordResetController extends Controller
{
    /** Issues a one-time temporary password; it is returned once and never stored in clear. */
    public function __invoke(Request $request, User $user, UserService $users): JsonResponse
    {
        Gate::authorize('resetPassword', $user);

        return ApiResponse::data(['temporary_password' => $users->resetPassword($user, $request->user())]);
    }
}
