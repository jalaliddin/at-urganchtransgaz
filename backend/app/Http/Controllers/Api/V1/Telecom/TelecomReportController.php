<?php

namespace App\Http\Controllers\Api\V1\Telecom;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Telecom\BillingService;
use App\Services\Telecom\TelecomReports;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** SIM counts per organization and operator; service costs per month. */
class TelecomReportController extends Controller
{
    public function __construct(private readonly TelecomReports $reports) {}

    public function sims(Request $request): JsonResponse
    {
        Gate::authorize('sim.use');

        return ApiResponse::data($this->reports->simSummary($this->user($request), (array) $request->input('filter', [])));
    }

    public function costs(Request $request): JsonResponse
    {
        Gate::authorize('services.use');
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'group_by' => ['nullable', Rule::in(TelecomReports::GROUPS)],
        ]);
        $year = (int) ($data['year'] ?? BillingService::today()->year);

        return ApiResponse::data([
            'year' => $year,
            ...$this->reports->costs($this->user($request), $year, $data['group_by'] ?? 'organization', (array) $request->input('filter', [])),
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
