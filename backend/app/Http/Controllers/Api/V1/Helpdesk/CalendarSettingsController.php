<?php

namespace App\Http\Controllers\Api\V1\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Helpdesk\CalendarSettingsRequest;
use App\Services\Settings\SettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** Business calendar and Helpdesk timing settings (helpdesk.* keys). */
class CalendarSettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function show(): JsonResponse
    {
        Gate::authorize('helpdesk.use');

        return ApiResponse::data($this->values());
    }

    public function update(CalendarSettingsRequest $request): JsonResponse
    {
        $this->settings->update($request->settingValues(), $request->user());

        return ApiResponse::data($this->values());
    }

    /** @return array<string, mixed> */
    private function values(): array
    {
        $all = $this->settings->all();
        $values = [];
        foreach (CalendarSettingsRequest::KEYS as $key) {
            $values[$key] = $all["helpdesk.{$key}"];
        }
        $values['timezone'] = config('app.display_timezone');

        return $values;
    }
}
