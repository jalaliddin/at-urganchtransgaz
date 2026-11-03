<?php

namespace App\Http\Controllers\Api\V1\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Resources\Helpdesk\TicketCommentResource;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Helpdesk\TicketWorkflow;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TicketCommentController extends Controller
{
    public function __construct(private readonly TicketWorkflow $workflow) {}

    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        /** @var User $user */
        $user = $request->user();

        $comments = $ticket->comments()
            ->with(['user', 'attachments.file', 'attachments.user'])
            ->when(! $this->workflow->isHandler($ticket, $user), fn ($q) => $q->where('is_internal', false))
            ->orderBy('id')
            ->get();

        return ApiResponse::data(TicketCommentResource::collection($comments));
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);
        /** @var User $user */
        $user = $request->user();

        $comment = $this->workflow->comment($ticket, $user, $data['body'], (bool) ($data['is_internal'] ?? false));

        return ApiResponse::data(new TicketCommentResource($comment->load(['user', 'attachments.file', 'attachments.user'])), 201);
    }
}
