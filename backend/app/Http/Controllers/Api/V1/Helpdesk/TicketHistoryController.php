<?php

namespace App\Http\Controllers\Api\V1\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Resources\Helpdesk\TicketHistoryResource;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Helpdesk\TicketWorkflow;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TicketHistoryController extends Controller
{
    public function __invoke(Request $request, Ticket $ticket, TicketWorkflow $workflow): JsonResponse
    {
        Gate::authorize('view', $ticket);
        /** @var User $user */
        $user = $request->user();

        $entries = $ticket->histories()->with('user')
            ->when(! $workflow->isHandler($ticket, $user), fn ($q) => $q->where('is_internal', false))
            ->orderBy('id')
            ->get();

        return ApiResponse::data(TicketHistoryResource::collection($entries));
    }
}
