<?php

namespace App\Http\Controllers\Api\V1\Helpdesk;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Helpdesk\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Helpdesk\TicketWorkflow;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Workflow actions on a ticket; each returns the updated ticket. */
class TicketActionController extends Controller
{
    public function __construct(private readonly TicketWorkflow $workflow) {}

    public function transition(Request $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(TicketStatus::class)],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->workflow->transition($ticket, TicketStatus::from($data['status']), $this->user($request), $data['note'] ?? null);

        return $this->respond($ticket);
    }

    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('assign', $ticket);
        $data = $request->validate(['assignee_id' => ['required', 'integer', Rule::exists('users', 'id')]]);

        $assignee = User::query()->withoutGlobalScopes()->findOrFail($data['assignee_id']);
        $this->workflow->assign($ticket, $assignee, $this->user($request));

        return $this->respond($ticket);
    }

    public function take(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('process', $ticket);
        $user = $this->user($request);

        $this->workflow->assign($ticket, $user, $user);

        return $this->respond($ticket);
    }

    public function rate(Request $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->workflow->rate($ticket, (int) $data['rating'], $data['comment'] ?? null, $this->user($request));

        return $this->respond($ticket);
    }

    public function time(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('process', $ticket);
        $data = $request->validate([
            'minutes' => ['required', 'integer', 'between:1,1440'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->workflow->logTime($ticket, (int) $data['minutes'], $data['note'] ?? null, $this->user($request));

        return $this->respond($ticket);
    }

    private function respond(Ticket $ticket): JsonResponse
    {
        return ApiResponse::data(new TicketResource($ticket->refresh()->load(TicketController::RELATIONS)));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
