<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Services\Settings\SettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(): JsonResponse
    {
        Gate::authorize('settings.view');

        return ApiResponse::data($this->settings->describe());
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $this->settings->update($request->settingValues(), $request->user());

        return ApiResponse::data($this->settings->describe());
    }
}
