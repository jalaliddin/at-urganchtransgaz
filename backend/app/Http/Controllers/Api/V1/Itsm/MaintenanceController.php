<?php

namespace App\Http\Controllers\Api\V1\Itsm;

use App\Enums\MaintenanceResult;
use App\Enums\MaintenanceTaskStatus;
use App\Enums\MaintenanceUnit;
use App\Http\Controllers\Controller;
use App\Http\Resources\Itsm\ItsmResource;
use App\Models\Asset;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceTask;
use App\Models\User;
use App\Rules\AccessibleOrganization;
use App\Services\Maintenance\MaintenanceService;
use App\Support\ApiResponse;
use App\Support\OrganizationSubtree;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Preventive maintenance plans and work items (design M1–M5). Route binding answers 404 outside the user's organizations. */
class MaintenanceController extends Controller
{
    public function __construct(private readonly MaintenanceService $maintenance) {}

    public function plans(Request $request): JsonResponse
    {
        Gate::authorize('maintenance.view');
        $query = $this->planQuery(MaintenancePlan::query()->visibleTo($this->user($request)));
        if ($request->filled('filter.organization')) {
            $query->whereIn('organization_id', OrganizationSubtree::ids($request->integer('filter.organization')));
        }
        if ($request->filled('filter.active')) {
            $query->where('is_active', $request->boolean('filter.active'));
        }
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('title', 'like', "%{$search}%");
        }
        $page = $query->orderByDesc('is_active')->orderBy('title')->paginate(Pagination::perPage($request))->withQueryString();

        return ApiResponse::page($page, fn (MaintenancePlan $p) => $this->planRow($p));
    }

    public function storePlan(Request $request): JsonResponse
    {
        Gate::authorize('maintenance.manage');
        $user = $this->user($request);
        $data = $request->validate(['organization_id' => ['required', 'integer', new AccessibleOrganization], ...$this->planRules(true)]);

        return $this->planDetail($this->maintenance->save(null, $data, $user), 201);
    }

    public function showPlan(MaintenancePlan $maintenancePlan): JsonResponse
    {
        Gate::authorize('maintenance.view');

        return $this->planDetail($maintenancePlan);
    }

    public function updatePlan(Request $request, MaintenancePlan $maintenancePlan): JsonResponse
    {
        Gate::authorize('maintenance.manage');

        return $this->planDetail($this->maintenance->save($maintenancePlan, $request->validate($this->planRules(false)), $this->user($request)));
    }

    public function destroyPlan(MaintenancePlan $maintenancePlan): JsonResponse
    {
        Gate::authorize('maintenance.manage');
        $this->maintenance->delete($maintenancePlan);

        return ApiResponse::message(__('messages.deleted'));
    }

    public function tasks(Request $request): JsonResponse
    {
        Gate::authorize('maintenance.view');
        $user = $this->user($request);
        $today = $this->maintenance->today()->toDateString();
        $query = MaintenanceTask::query()->visibleTo($user)->with(['plan.assignee', 'asset.category', 'organization', 'completer']);
        if ($request->filled('filter.status')) {
            $query->whereIn('status', explode(',', (string) $request->input('filter.status')));
        }
        if ($request->boolean('filter.overdue')) {
            $query->where('status', MaintenanceTaskStatus::Planned->value)->where('due_on', '<', $today);
        }
        foreach (['plan' => 'plan_id', 'asset' => 'asset_id'] as $filter => $column) {
            if ($request->filled("filter.{$filter}")) {
                $query->where($column, $request->integer("filter.{$filter}"));
            }
        }
        if ($request->filled('filter.organization')) {
            $query->whereIn('organization_id', OrganizationSubtree::ids($request->integer('filter.organization')));
        }
        if ($request->filled('filter.due_from')) {
            $query->where('due_on', '>=', (string) $request->input('filter.due_from'));
        }
        if ($request->filled('filter.due_to')) {
            $query->where('due_on', '<=', (string) $request->input('filter.due_to'));
        }
        if ($request->boolean('filter.mine')) {
            $query->whereIn('plan_id', MaintenancePlan::query()->withoutGlobalScopes()->select('id')->where('assignee_id', $user->id));
        }
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->whereHas('asset', fn ($q) => $q->where('inventory_number', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
        }
        $page = $query->orderByRaw("FIELD(status, 'planned', 'done', 'skipped', 'cancelled')")->orderBy('due_on')->orderBy('id')
            ->paginate(Pagination::perPage($request))->withQueryString();

        return ApiResponse::page($page, fn (MaintenanceTask $t) => $this->taskRow($t, $user, $today));
    }

    public function complete(Request $request, MaintenanceTask $maintenanceTask): JsonResponse
    {
        Gate::authorize('maintenance.perform');
        $user = $this->user($request);
        $data = $request->validate([
            'result' => ['required', Rule::enum(MaintenanceResult::class)],
            'checklist' => ['present', 'array', 'max:50'],
            'checklist.*.done' => ['required', 'boolean'],
            'checklist.*.note' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:5000'],
            'completed_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$this->maintenance->today()->toDateString()],
        ]);

        return $this->taskDetail($this->maintenance->complete($maintenanceTask, $data, $user), $user);
    }

    public function skip(Request $request, MaintenanceTask $maintenanceTask): JsonResponse
    {
        Gate::authorize('maintenance.perform');
        $user = $this->user($request);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        return $this->taskDetail($this->maintenance->skip($maintenanceTask, (string) $data['reason'], $user), $user);
    }

    /**
     * @param  Builder<MaintenancePlan>  $query
     * @return Builder<MaintenancePlan>
     */
    private function planQuery(Builder $query): Builder
    {
        $planned = fn ($q) => $q->where('status', MaintenanceTaskStatus::Planned->value);

        return $query->with(['organization', 'assignee'])->withCount('assets')
            ->withMin(['tasks as next_due' => $planned], 'due_on')
            ->withCount(['tasks as overdue_count' => fn ($q) => $planned($q)->where('due_on', '<', $this->maintenance->today()->toDateString())]);
    }

    /** @return array<string, mixed> */
    private function planRow(MaintenancePlan $p): array
    {
        $attributes = $p->getAttributes();

        return [
            'id' => $p->id,
            'title' => $p->title,
            'organization' => ItsmResource::organization($p->organization),
            'interval_count' => $p->interval_count,
            'interval_unit' => $p->interval_unit->value,
            'start_on' => $p->start_on->toDateString(),
            'assignee' => ItsmResource::user($p->assignee),
            'lead_days' => $p->lead_days,
            'is_active' => $p->is_active,
            'assets_count' => (int) ($attributes['assets_count'] ?? 0),
            'next_due' => isset($attributes['next_due']) ? substr((string) $attributes['next_due'], 0, 10) : null,
            'overdue_count' => (int) ($attributes['overdue_count'] ?? 0),
        ];
    }

    private function planDetail(MaintenancePlan $plan, int $status = 200): JsonResponse
    {
        $p = $this->planQuery(MaintenancePlan::query()->withoutGlobalScopes())->with('assets.category')->findOrFail($plan->id);

        return ApiResponse::data([
            ...$this->planRow($p),
            'description' => $p->description,
            'checklist' => $p->checklist ?? [],
            'assets' => $p->assets->map(fn (Asset $a) => ['id' => $a->id, 'inventory_number' => $a->inventory_number, 'name' => $a->name,
                'category' => $a->category?->localizedName(), 'status' => $a->status->value])->values()->all(),
        ], $status);
    }

    /** @return array<string, mixed> */
    private function taskRow(MaintenanceTask $t, User $user, string $today): array
    {
        $planned = $t->status === MaintenanceTaskStatus::Planned;

        return [
            'id' => $t->id,
            'plan' => ['id' => $t->plan->id, 'title' => $t->plan->title, 'checklist' => $t->plan->checklist ?? [],
                'assignee' => ItsmResource::user($t->plan->assignee)],
            'asset' => ['id' => $t->asset->id, 'inventory_number' => $t->asset->inventory_number, 'name' => $t->asset->name,
                'category' => $t->asset->category?->localizedName()],
            'organization' => ItsmResource::organization($t->organization),
            'due_on' => $t->due_on->toDateString(),
            'status' => $t->status->value,
            'overdue' => $planned && $t->due_on->toDateString() < $today,
            'completed_on' => $t->completed_on?->toDateString(),
            'completed_by' => ItsmResource::user($t->completer),
            'result' => $t->result?->value,
            'checklist' => $t->checklist,
            'note' => $t->note,
            'can' => ['perform' => $planned && $user->can('maintenance.perform')],
        ];
    }

    private function taskDetail(MaintenanceTask $task, User $user): JsonResponse
    {
        $t = MaintenanceTask::query()->withoutGlobalScopes()->with(['plan.assignee', 'asset.category', 'organization', 'completer'])->findOrFail($task->id);

        return ApiResponse::data($this->taskRow($t, $user, $this->maintenance->today()->toDateString()));
    }

    /** @return array<string, mixed> */
    private function planRules(bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'checklist' => ['sometimes', 'array', 'max:50'],
            'checklist.*' => ['nullable', 'string', 'max:255'],
            'interval_count' => [$required, 'integer', 'min:1', 'max:120'],
            'interval_unit' => [$required, Rule::enum(MaintenanceUnit::class)],
            'start_on' => [$required, 'date_format:Y-m-d'],
            'assignee_id' => ['nullable', 'integer'],
            'lead_days' => ['sometimes', 'integer', 'min:0', 'max:90'],
            'is_active' => ['sometimes', 'boolean'],
            'asset_ids' => [$creating ? 'required' : 'sometimes', 'array', 'min:1', 'max:500'],
            'asset_ids.*' => ['integer'],
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
