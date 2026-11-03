<?php

namespace App\Http\Controllers\Api\V1\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Helpdesk\StoreTicketRequest;
use App\Http\Requests\Helpdesk\UpdateTicketRequest;
use App\Http\Resources\Helpdesk\TicketListResource;
use App\Http\Resources\Helpdesk\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Helpdesk\TicketFilters;
use App\Services\Helpdesk\TicketService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use App\Support\Search\SearchTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\QueryBuilder;

class TicketController extends Controller
{
    public const LIST_RELATIONS = ['organization', 'category', 'assignee', 'requesterEmployee', 'requesterUser'];

    public const RELATIONS = [...self::LIST_RELATIONS, 'department'];

    public function __construct(private readonly TicketService $tickets) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Ticket::class);
        /** @var User $user */
        $user = $request->user();

        $base = Ticket::query()->visibleTo($user)->with(self::LIST_RELATIONS);
        SearchTerm::whereContains($base, ['number', 'title', 'contact_name', 'contact_phone'], SearchTerm::fromRequest($request));

        $page = QueryBuilder::for($base)
            ->allowedFilters(TicketFilters::allowed($user))
            ->allowedSorts(['created_at', 'priority', 'resolution_due_at', 'number', 'status'])
            ->defaultSort('-created_at')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return ApiResponse::paginated($page, TicketListResource::class);
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = $this->tickets->create($request->ticketData(), $user);

        return ApiResponse::data(new TicketResource($ticket->load(self::RELATIONS)), 201);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        return ApiResponse::data(new TicketResource($ticket->load(self::RELATIONS)));
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = $this->tickets->update($ticket, $request->validated(), $user);

        return ApiResponse::data(new TicketResource($ticket->load(self::RELATIONS)));
    }
}
