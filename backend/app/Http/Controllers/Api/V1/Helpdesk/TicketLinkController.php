<?php

namespace App\Http\Controllers\Api\V1\Helpdesk;

use App\Enums\TicketHistoryAction;
use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketLink;
use App\Models\User;
use App\Services\Helpdesk\TicketService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Links between related or duplicate tickets ("takroriy muammolarni bog'lash"). */
class TicketLinkController extends Controller
{
    public function __construct(private readonly TicketService $tickets) {}

    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        /** @var User $user */
        $user = $request->user();

        $links = TicketLink::query()
            ->where(fn ($q) => $q->where('ticket_id', $ticket->id)->orWhere('linked_ticket_id', $ticket->id))
            ->orderBy('id')->get();
        $visible = Ticket::query()->visibleTo($user)
            ->whereIn('id', $links->map(fn (TicketLink $l) => $l->otherId($ticket->id)))
            ->get(['id', 'number', 'title', 'status'])->keyBy('id');

        $rows = $links->filter(fn (TicketLink $l) => $visible->has($l->otherId($ticket->id)))
            ->map(fn (TicketLink $l) => $this->row($l, $visible[$l->otherId($ticket->id)]))
            ->values();

        return ApiResponse::data($rows);
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('process', $ticket);
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'linked_ticket_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(TicketLink::TYPES)],
        ]);

        $other = Ticket::query()->visibleTo($user)->find((int) $data['linked_ticket_id']);
        if ($other === null || $other->id === $ticket->id) {
            throw ValidationException::withMessages(['linked_ticket_id' => __('messages.ticket_link_invalid')]);
        }
        $exists = TicketLink::query()->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('ticket_id', $ticket->id)->where('linked_ticket_id', $other->id))
            ->orWhere(fn ($q) => $q->where('ticket_id', $other->id)->where('linked_ticket_id', $ticket->id)))->exists();
        if ($exists) {
            throw new DomainConflictException(__('messages.ticket_link_exists'));
        }

        $link = DB::transaction(function () use ($ticket, $other, $data, $user): TicketLink {
            $link = TicketLink::create([
                'ticket_id' => $ticket->id, 'linked_ticket_id' => $other->id, 'type' => $data['type'], 'created_by' => $user->id,
            ]);
            $this->tickets->history($ticket, $user, TicketHistoryAction::Linked, new: ['ticket' => $other->number, 'type' => $data['type']]);
            $this->tickets->history($other, $user, TicketHistoryAction::Linked, new: ['ticket' => $ticket->number, 'type' => $data['type']]);

            return $link;
        });

        return ApiResponse::data($this->row($link, $other), 201);
    }

    public function destroy(Request $request, Ticket $ticket, int $link): JsonResponse
    {
        Gate::authorize('process', $ticket);
        /** @var User $user */
        $user = $request->user();
        $row = TicketLink::query()
            ->where(fn ($q) => $q->where('ticket_id', $ticket->id)->orWhere('linked_ticket_id', $ticket->id))
            ->find($link) ?? throw new NotFoundHttpException;
        $other = Ticket::query()->withoutGlobalScopes()->findOrFail($row->otherId($ticket->id));

        DB::transaction(function () use ($row, $ticket, $other, $user): void {
            $row->delete();
            $this->tickets->history($ticket, $user, TicketHistoryAction::Unlinked, old: ['ticket' => $other->number]);
            $this->tickets->history($other, $user, TicketHistoryAction::Unlinked, old: ['ticket' => $ticket->number]);
        });

        return ApiResponse::message(__('messages.deleted'));
    }

    /** @return array<string, mixed> */
    private function row(TicketLink $link, Ticket $other): array
    {
        return [
            'id' => $link->id,
            'type' => $link->type,
            'ticket' => ['id' => $other->id, 'number' => $other->number, 'title' => $other->title, 'status' => $other->status->value],
        ];
    }
}
