<?php

namespace App\Http\Controllers\Api\V1\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Helpdesk\TicketFilters;
use App\Services\Helpdesk\TicketStats;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Helpdesk report: SLA compliance and breakdowns (spec §5.15). */
class StatsController extends Controller
{
    public function __invoke(Request $request, TicketStats $stats): JsonResponse
    {
        Gate::authorize('helpdesk.view');
        $request->validate([
            'filter' => ['sometimes', 'array:'.implode(',', TicketFilters::NAMES)],
        ]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::data($stats->summary($user, (array) $request->input('filter', [])));
    }
}
