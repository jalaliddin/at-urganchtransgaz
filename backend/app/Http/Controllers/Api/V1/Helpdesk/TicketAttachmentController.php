<?php

namespace App\Http\Controllers\Api\V1\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Resources\Helpdesk\TicketAttachmentResource;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;
use App\Rules\SafeUpload;
use App\Services\Helpdesk\TicketAbilities;
use App\Services\Helpdesk\TicketAttachments;
use App\Services\Helpdesk\TicketWorkflow;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TicketAttachmentController extends Controller
{
    public function __construct(
        private readonly TicketAttachments $attachments,
        private readonly TicketWorkflow $workflow,
    ) {}

    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        $handler = $this->workflow->isHandler($ticket, $this->user($request));

        $rows = TicketAttachment::query()->where('ticket_id', $ticket->id)->with(['file', 'user', 'comment'])
            ->orderBy('id')->get()
            ->filter(fn (TicketAttachment $a): bool => $handler || ! $a->comment?->is_internal)
            ->values();

        return ApiResponse::data(TicketAttachmentResource::collection($rows));
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $this->user($request);
        if (! in_array('attach', app(TicketAbilities::class)->for($ticket, $user), true)) {
            throw new AuthorizationException;
        }
        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => [new SafeUpload],
            'comment_id' => ['nullable', 'integer'],
        ]);

        $comment = null;
        if (! empty($data['comment_id'])) {
            $comment = TicketComment::query()->where('ticket_id', $ticket->id)->find((int) $data['comment_id']);
            if ($comment === null || ($comment->is_internal && ! $this->workflow->isHandler($ticket, $user))) {
                throw ValidationException::withMessages(['comment_id' => __('validation.exists', ['attribute' => 'comment_id'])]);
            }
        }

        $created = DB::transaction(fn () => $this->attachments->attach(
            $ticket, $request->file('files', []), $user, $comment?->id, (bool) $comment?->is_internal,
        ));

        return ApiResponse::data(TicketAttachmentResource::collection($created->load(['file', 'user'])), 201);
    }

    public function destroy(Request $request, Ticket $ticket, int $attachment): JsonResponse
    {
        $user = $this->user($request);
        $row = TicketAttachment::query()->where('ticket_id', $ticket->id)->with(['file', 'comment'])->find($attachment);
        if ($row === null || ($row->comment?->is_internal && ! $this->workflow->isHandler($ticket, $user))) {
            throw new NotFoundHttpException;
        }
        if (! $this->attachments->canDelete($row, $ticket, $user)) {
            throw new AuthorizationException;
        }

        DB::transaction(fn () => $this->attachments->delete($row, $ticket, $user));

        return ApiResponse::message(__('messages.deleted'));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
