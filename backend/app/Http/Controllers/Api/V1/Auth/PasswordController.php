<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Services\Auth\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PasswordController extends Controller
{
    public function update(ChangePasswordRequest $request, AuthService $auth): JsonResponse
    {
        $auth->changePassword($request, $request->user(), $request->string('password')->toString());

        return ApiResponse::message(__('auth.password_changed'));
    }
}
