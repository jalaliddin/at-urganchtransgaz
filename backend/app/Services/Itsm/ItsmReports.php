<?php

namespace App\Services\Itsm;

use App\Enums\ChangeStatus;
use App\Enums\MaintenanceTaskStatus;
use App\Enums\ProblemStatus;
use App\Models\Change;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceTask;
use App\Models\Problem;
use App\Models\User;
use App\Services\Problems\ProblemService;
use App\Support\OrganizationSubtree;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Dashboard blocks and export queries of problems, changes and preventive maintenance. */
class ItsmReports
{
    public function __construct(private readonly ProblemService $problems) {}

    /** @return array{open: int, mine: int, known_errors: int, candidates: int} */
    public function problemsWidget(User $user, ?int $organizationId): array
    {
        $open = fn () => $this->within(Problem::query()->visibleTo($user), $organizationId)->whereIn('status', ProblemStatus::openValues());

        return [
            'open' => $open()->count(),
            'mine' => $open()->where('assignee_id', $user->id)->count(),
            'known_errors' => $open()->where('status', ProblemStatus::KnownError->value)->count(),
            'candidates' => count($this->problems->candidates($user, $organizationId)),
        ];
    }

    /** @return array{pending_approval: int, this_week: int, in_progress: int} */
    public function changesWidget(User $user, ?int $organizationId): array
    {
        $now = CarbonImmutable::now();
        $changes = fn () => $this->within(Change::query()->visibleTo($user), $organizationId);

        return [
            'pending_approval' => $changes()->where('status', ChangeStatus::PendingApproval->value)->count(),
            'this_week' => $changes()->whereIn('status', [ChangeStatus::Approved->value, ChangeStatus::InProgress->value])
                ->whereBetween('planned_start', [$now, $now->addDays(7)])->count(),
            'in_progress' => $changes()->where('status', ChangeStatus::InProgress->value)->count(),
        ];
    }

    /** @return array{due_week: int, overdue: int} */
    public function maintenanceWidget(User $user, ?int $organizationId): array
    {
        $today = CarbonImmutable::today((string) config('app.display_timezone'));
        $planned = fn () => $this->within(MaintenanceTask::query()->visibleTo($user), $organizationId)
            ->where('status', MaintenanceTaskStatus::Planned->value)
            ->whereIn('plan_id', MaintenancePlan::query()->withoutGlobalScopes()->select('id')->where('is_active', true));

        return [
            'due_week' => $planned()->whereBetween('due_on', [$today->toDateString(), $today->addDays(7)->toDateString()])->count(),
            'overdue' => $planned()->where('due_on', '<', $today->toDateString())->count(),
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function within(Builder $query, ?int $organizationId): Builder
    {
        return $query->when($organizationId !== null, fn ($q) => $q->whereIn($q->qualifyColumn('organization_id'), OrganizationSubtree::ids((int) $organizationId) ?: [0]));
    }
}
