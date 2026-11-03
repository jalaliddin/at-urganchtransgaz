<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Services\Auth\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ForgotPasswordController extends Controller
{
    /** Always answers the same way so account existence is never revealed. */
    public function __invoke(ForgotPasswordRequest $request, AuthService $auth): JsonResponse
    {
        $auth->sendResetLink($request->string('login')->toString());

        return ApiResponse::message(__('passwords.sent'));
    }
}
