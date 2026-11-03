<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Auth\MeResource;
use App\Services\Auth\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function store(LoginRequest $request): JsonResponse
    {
        $user = $this->auth->login(
            $request,
            $request->string('username')->toString(),
            $request->string('password')->toString(),
            $request->boolean('remember'),
        );

        return ApiResponse::data(new MeResource($user));
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->auth->logout($request, $request->user());

        return ApiResponse::message(__('auth.logged_out'));
    }
}
