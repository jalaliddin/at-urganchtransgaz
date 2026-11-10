<?php

namespace App\Http\Controllers\Api\V1\Itsm;

use App\Enums\ChangeCategory;
use App\Enums\ChangeRisk;
use App\Enums\ChangeStatus;
use App\Enums\ChangeType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Itsm\ItsmResource;
use App\Models\Asset;
use App\Models\Change;
use App\Models\ChangeHistory;
use App\Models\User;
use App\Rules\AccessibleOrganization;
use App\Services\Changes\ChangeService;
use App\Support\ApiResponse;
use App\Support\OrganizationSubtree;
use App\Support\Pagination;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Change management (design C1–C6). Route binding answers 404 outside the user's organizations. */
class ChangeController extends Controller
{
    private const RELATIONS = ['organization', 'implementer', 'creator'];

    public function __construct(private readonly ChangeService $changes) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('changes.view');
        $query = Change::query()->visibleTo($this->user($request))->with(self::RELATIONS)->withCount('assets');
        foreach (['status' => 'status', 'type' => 'type', 'category' => 'category', 'risk' => 'risk'] as $filter => $column) {
            if ($request->filled("filter.{$filter}")) {
                $query->whereIn($column, explode(',', (string) $request->input("filter.{$filter}")));
            }
        }
        if ($request->filled('filter.organization')) {
            $query->whereIn('organization_id', OrganizationSubtree::ids($request->integer('filter.organization')));
        }
        if ($request->filled('filter.asset')) {
            $query->whereHas('assets', fn ($q) => $q->whereKey($request->integer('filter.asset')));
        }
        if ($request->filled('filter.implementer')) {
            $query->where('implementer_id', $request->integer('filter.implementer'));
        }
        if ($request->filled('filter.from')) {
            $query->where('planned_end', '>=', CarbonImmutable::parse((string) $request->input('filter.from'), (string) config('app.display_timezone'))->utc());
        }
        if ($request->filled('filter.to')) {
            $query->where('planned_start', '<=', CarbonImmutable::parse((string) $request->input('filter.to'), (string) config('app.display_timezone'))->endOfDay()->utc());
        }
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('number', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%"));
        }
        $page = $query->orderByRaw('planned_start IS NULL')->orderByDesc('planned_start')->orderByDesc('id')
            ->paginate(Pagination::perPage($request))->withQueryString();

        return ApiResponse::page($page, fn (Change $c) => $this->row($c));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('changes.create');
        $user = $this->user($request);
        $data = $request->validate([
            'organization_id' => ['required', 'integer', new AccessibleOrganization],
            ...$this->rules($user, true),
        ]);

        return $this->detail($this->changes->create($data, $user), $user, 201);
    }

    public function show(Request $request, Change $change): JsonResponse
    {
        Gate::authorize('changes.view');

        return $this->detail($change, $this->user($request));
    }

    public function update(Request $request, Change $change): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($this->changes->canEdit($change, $user), 403);

        return $this->detail($this->changes->update($change, $request->validate($this->rules($user, false)), $user), $user);
    }

    public function submit(Request $request, Change $change): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($this->changes->canEdit($change, $user), 403);

        return $this->detail($this->changes->submit($change, $user), $user);
    }

    public function approve(Request $request, Change $change): JsonResponse
    {
        return $this->decide($request, $change, true);
    }

    public function reject(Request $request, Change $change): JsonResponse
    {
        return $this->decide($request, $change, false);
    }

    public function start(Request $request, Change $change): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($this->changes->canRun($change, $user), 403);

        return $this->detail($this->changes->start($change, $user), $user);
    }

    public function complete(Request $request, Change $change): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($this->changes->canRun($change, $user), 403);
        $data = $request->validate(['success' => ['required', 'boolean'], 'result' => ['required', 'string', 'max:10000']]);

        return $this->detail($this->changes->complete($change, (bool) $data['success'], (string) $data['result'], $user), $user);
    }

    public function cancel(Request $request, Change $change): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($this->changes->canEdit($change, $user), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        return $this->detail($this->changes->cancel($change, (string) $data['reason'], $user), $user);
    }

    public function revise(Request $request, Change $change): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($this->changes->canEdit($change, $user), 403);

        return $this->detail($this->changes->revise($change, $user), $user);
    }

    public function comment(Request $request, Change $change): JsonResponse
    {
        Gate::authorize('changes.view');
        $user = $this->user($request);
        abort_unless($user->canAny(['changes.create', 'changes.approve', 'changes.manage']), 403);
        $this->changes->comment($change, (string) $request->validate(['comment' => ['required', 'string', 'max:5000']])['comment'], $user);

        return $this->detail($change, $user, 201);
    }

    private function decide(Request $request, Change $change, bool $approve): JsonResponse
    {
        Gate::authorize('changes.approve');
        $user = $this->user($request);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:5000']]);

        return $this->detail($this->changes->decide($change, $approve, $data['comment'] ?? null, $user), $user);
    }

    /** @return array<string, mixed> */
    private function row(Change $c): array
    {
        return [
            'id' => $c->id,
            'number' => $c->number,
            'title' => $c->title,
            'organization' => ItsmResource::organization($c->organization),
            'type' => $c->type->value,
            'category' => $c->category->value,
            'risk' => $c->risk->value,
            'status' => $c->status->value,
            'planned_start' => $c->planned_start?->toIso8601String(),
            'planned_end' => $c->planned_end?->toIso8601String(),
            'implementer' => ItsmResource::user($c->implementer),
            'creator' => ItsmResource::user($c->creator),
            'assets_count' => (int) ($c->getAttributes()['assets_count'] ?? 0),
        ];
    }

    private function detail(Change $change, User $user, int $status = 200): JsonResponse
    {
        $c = Change::query()->withoutGlobalScopes()->with([...self::RELATIONS, 'decider', 'problem', 'assets.model', 'histories.user'])
            ->withCount('assets')->findOrFail($change->id);
        $edit = $this->changes->canEdit($c, $user);
        $run = $this->changes->canRun($c, $user);
        $approve = $user->can('changes.approve') && $c->created_by !== $user->id;

        return ApiResponse::data([
            ...$this->row($c),
            'description' => $c->description,
            'impact' => $c->impact,
            'plan' => $c->plan,
            'backout_plan' => $c->backout_plan,
            'actual_start' => $c->actual_start?->toIso8601String(),
            'actual_end' => $c->actual_end?->toIso8601String(),
            'submitted_at' => $c->submitted_at?->toIso8601String(),
            'decided_by' => ItsmResource::user($c->decider),
            'decided_at' => $c->decided_at?->toIso8601String(),
            'decision_comment' => $c->decision_comment,
            'result' => $c->result,
            'problem' => $c->problem === null ? null : ['id' => $c->problem->id, 'number' => $c->problem->number, 'title' => $c->problem->title],
            'assets' => $c->assets->map(fn (Asset $a) => ['id' => $a->id, 'inventory_number' => $a->inventory_number, 'name' => $a->name,
                'model' => $a->model?->name])->values()->all(),
            'conflicts' => $this->changes->conflicts($c, $user)->map(fn (Change $o) => ItsmResource::changeRef($o))->values()->all(),
            'history' => $c->histories->sortByDesc('id')->map(fn (ChangeHistory $h) => ItsmResource::changeHistory($h))->values()->all(),
            'can' => [
                'edit' => $edit && $c->status === ChangeStatus::Draft,
                'submit' => $edit && $c->status === ChangeStatus::Draft,
                'decide' => $approve && $c->status === ChangeStatus::PendingApproval,
                'start' => $run && $c->status === ChangeStatus::Approved,
                'complete' => $run && $c->status === ChangeStatus::InProgress,
                'cancel' => $edit && in_array($c->status, [ChangeStatus::Draft, ChangeStatus::PendingApproval, ChangeStatus::Approved], true),
                'revise' => $edit && $c->status === ChangeStatus::Rejected,
                'comment' => $user->canAny(['changes.create', 'changes.approve', 'changes.manage']),
            ],
        ], $status);
    }

    /** @return array<string, mixed> */
    private function rules(User $user, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'type' => [$required, Rule::enum(ChangeType::class)],
            'category' => [$required, Rule::enum(ChangeCategory::class)],
            'risk' => [$required, Rule::enum(ChangeRisk::class)],
            'impact' => ['nullable', 'string', 'max:10000'],
            'plan' => ['nullable', 'string', 'max:20000'],
            'backout_plan' => ['nullable', 'string', 'max:20000'],
            'planned_start' => ['nullable', 'date'],
            'planned_end' => ['nullable', 'date'],
            'implementer_id' => ['nullable', 'integer'],
            'problem_id' => ['nullable', 'integer', $this->visibleProblem($user)],
            'asset_ids' => ['sometimes', 'array', 'max:100'],
            'asset_ids.*' => ['integer'],
        ];
    }

    private function visibleProblem(User $user): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($user): void {
            if (! $this->changes->problemVisible($user, (int) $value)) {
                $fail(__('messages.problem_not_accessible'));
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
