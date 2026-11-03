<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Services\Settings\SettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PublicSettingsController extends Controller
{
    /** Shown on the login page: system/organization name and support contacts. */
    public function __invoke(SettingsService $settings): JsonResponse
    {
        return ApiResponse::data($settings->publicValues());
    }
}
