<?php

namespace App\Http\Controllers\Api\V1\Itsm;

use App\Enums\ProblemPriority;
use App\Enums\ProblemStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Itsm\ItsmResource;
use App\Models\Change;
use App\Models\Organization;
use App\Models\Problem;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Rules\AccessibleOrganization;
use App\Services\Problems\ProblemService;
use App\Support\ApiResponse;
use App\Support\OrganizationSubtree;
use App\Support\Pagination;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Problem management (design R1–R6). Route binding answers 404 outside the user's organizations. */
class ProblemController extends Controller
{
    private const RELATIONS = ['organization', 'category', 'assignee'];

    public function __construct(private readonly ProblemService $problems) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('problems.view');
        $query = Problem::query()->visibleTo($this->user($request))->with(self::RELATIONS)->withCount('tickets');
        if ($request->filled('filter.status')) {
            $query->whereIn('status', explode(',', (string) $request->input('filter.status')));
        }
        if ($request->boolean('filter.open')) {
            $query->whereIn('status', ProblemStatus::openValues());
        }
        if ($request->filled('filter.priority')) {
            $query->whereIn('priority', explode(',', (string) $request->input('filter.priority')));
        }
        if ($request->filled('filter.organization')) {
            $query->whereIn('organization_id', OrganizationSubtree::ids($request->integer('filter.organization')));
        }
        if ($request->filled('filter.assignee')) {
            $query->where('assignee_id', $request->integer('filter.assignee'));
        }
        if ($request->filled('filter.category')) {
            $query->where('category_id', $request->integer('filter.category'));
        }
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('number', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%"));
        }
        $page = $query->orderByRaw("FIELD(status, 'new', 'investigating', 'known_error', 'resolved', 'closed')")
            ->orderByRaw("FIELD(priority, 'critical', 'high', 'normal', 'low')")->orderByDesc('id')
            ->paginate(Pagination::perPage($request))->withQueryString();

        return ApiResponse::page($page, fn (Problem $p) => ItsmResource::problem($p));
    }

    /** Recurring incidents not yet covered by an open problem (design R4). */
    public function candidates(Request $request): JsonResponse
    {
        Gate::authorize('problems.view');
        $rows = $this->problems->candidates($this->user($request), $request->filled('filter.organization') ? $request->integer('filter.organization') : null);
        $categories = TicketCategory::query()->whereIn('id', array_column($rows, 'category_id'))->get()->keyBy('id');
        $organizations = Organization::query()->withoutGlobalScopes()->whereIn('id', array_column($rows, 'organization_id'))->get()->keyBy('id');

        return ApiResponse::data(array_map(fn (array $r) => [
            'category' => ItsmResource::category($categories->get($r['category_id'])),
            'organization' => ($o = $organizations->get($r['organization_id'])) ? ItsmResource::organization($o) : null,
            'count' => $r['count'],
            'ticket_ids' => $r['ticket_ids'],
            'last_at' => $r['last_at'],
        ], $rows));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('problems.manage');
        $user = $this->user($request);
        $data = $request->validate([
            'organization_id' => ['required', 'integer', new AccessibleOrganization],
            ...$this->rules($user),
            'ticket_ids' => ['sometimes', 'array', 'max:200'],
            'ticket_ids.*' => ['integer'],
        ]);

        return $this->detail($this->problems->create($data, $user), $user, 201);
    }

    public function show(Request $request, Problem $problem): JsonResponse
    {
        Gate::authorize('problems.view');

        return $this->detail($problem, $this->user($request));
    }

    public function update(Request $request, Problem $problem): JsonResponse
    {
        Gate::authorize('problems.manage');
        $user = $this->user($request);
        $data = $request->validate([
            ...$this->rules($user),
            'root_cause' => ['nullable', 'string', 'max:10000'],
            'workaround' => ['nullable', 'string', 'max:10000'],
            'resolution' => ['nullable', 'string', 'max:10000'],
            'kb_article_id' => ['nullable', 'integer', $this->readableArticle($user)],
        ]);

        return $this->detail($this->problems->update($problem, $data, $user), $user);
    }

    public function transition(Request $request, Problem $problem): JsonResponse
    {
        Gate::authorize('problems.manage');
        $user = $this->user($request);
        $data = $request->validate([
            'status' => ['required', Rule::enum(ProblemStatus::class)],
            'root_cause' => ['nullable', 'string', 'max:10000'],
            'workaround' => ['nullable', 'string', 'max:10000'],
            'resolution' => ['nullable', 'string', 'max:10000'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->detail($this->problems->transition($problem, ProblemStatus::from((string) $data['status']), $data, $user), $user);
    }

    public function link(Request $request, Problem $problem): JsonResponse
    {
        Gate::authorize('problems.manage');
        $user = $this->user($request);
        $data = $request->validate(['ticket_ids' => ['required', 'array', 'min:1', 'max:200'], 'ticket_ids.*' => ['integer']]);

        return $this->detail($this->problems->link($problem, $data['ticket_ids'], $user), $user);
    }

    public function unlink(Request $request, Problem $problem, int $ticket): JsonResponse
    {
        Gate::authorize('problems.manage');
        $user = $this->user($request);
        $linked = $problem->tickets()->whereKey($ticket)->firstOrFail();

        return $this->detail($this->problems->unlink($problem, $linked, $user), $user);
    }

    public function comment(Request $request, Problem $problem): JsonResponse
    {
        Gate::authorize('problems.manage');
        $user = $this->user($request);
        $this->problems->comment($problem, (string) $request->validate(['comment' => ['required', 'string', 'max:5000']])['comment'], $user);

        return $this->detail($problem, $user, 201);
    }

    /** Problems a ticket belongs to (ticket page panel). */
    public function forTicket(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('problems.view');
        $problems = Problem::query()->visibleTo($this->user($request))->with(self::RELATIONS)->withCount('tickets')
            ->whereIn('id', $ticket->belongsToMany(Problem::class, 'problem_tickets', 'ticket_id', 'problem_id')->select('problems.id'))
            ->orderByDesc('id')->get();

        return ApiResponse::data($problems->map(fn (Problem $p) => ItsmResource::problem($p))->values());
    }

    private function detail(Problem $problem, User $user, int $status = 200): JsonResponse
    {
        $fresh = Problem::query()->withoutGlobalScopes()->with([...self::RELATIONS, 'creator', 'article', 'tickets.organization', 'histories.user'])
            ->withCount('tickets')->findOrFail($problem->id);
        $changes = Change::query()->visibleTo($user)->where('problem_id', $fresh->id)->orderByDesc('id')->get()
            ->map(fn (Change $c) => ItsmResource::changeRef($c))->values()->all();
        $manage = $user->can('problems.manage');

        return ApiResponse::data(ItsmResource::problemDetail($fresh, $changes, [
            'manage' => $manage,
            'transitions' => $manage ? array_map(fn (ProblemStatus $s) => $s->value, $fresh->status->next()) : [],
        ]), $status);
    }

    /** @return array<string, mixed> */
    private function rules(User $user): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category_id' => ['nullable', 'integer', Rule::exists('ticket_categories', 'id')],
            'priority' => ['required', Rule::enum(ProblemPriority::class)],
            'assignee_id' => ['nullable', 'integer'],
        ];
    }

    private function readableArticle(User $user): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($user): void {
            if (! $this->problems->articleVisible($user, (int) $value)) {
                $fail(__('messages.kb_article_not_accessible'));
            }
        };
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
