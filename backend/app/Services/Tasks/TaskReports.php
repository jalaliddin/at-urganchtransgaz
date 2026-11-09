<?php

namespace App\Services\Tasks;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\Support\OrganizationSubtree;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Workload and dashboard figures over the tasks a user can see (explicit scope, works in queued exports). */
class TaskReports
{
    /**
     * Per responsible person (plus "unassigned") and per organization: open, overdue, due within 7 days, done in 30 days.
     *
     * @param  array<string, mixed>  $filters  organization, board
     * @return array{by_assignee: list<array<string, mixed>>, by_organization: list<array<string, mixed>>}
     */
    public function workload(User $user, array $filters): array
    {
        $byAssignee = $this->counts($user, $filters, 'assignee_id');
        $users = User::query()->withoutGlobalScopes()->whereIn('id', $byAssignee->keys()->filter())->pluck('name', 'id');
        $byOrganization = $this->counts($user, $filters, 'organization_id');
        $organizations = Organization::query()->withoutGlobalScopes()->whereIn('id', $byOrganization->keys())->pluck('name', 'id');

        return [
            'by_assignee' => $byAssignee->map(fn (array $c, $id) => ['user' => $id === '' ? null : ['id' => (int) $id, 'name' => (string) $users->get($id)], ...$c])
                ->sortByDesc('open')->values()->all(),
            'by_organization' => $byOrganization->map(fn (array $c, $id) => ['organization' => ['id' => (int) $id, 'name' => (string) $organizations->get($id)], ...$c])
                ->sortByDesc('open')->values()->all(),
        ];
    }

    /** @return array<string, int> */
    public function widget(User $user, ?int $organizationId): array
    {
        $today = $this->today();
        $open = fn () => $this->scoped($user, ['organization' => $organizationId])->whereNull('completed_at');

        return [
            'my_open' => $open()->where('assignee_id', $user->id)->count(),
            'my_overdue' => $open()->where('assignee_id', $user->id)->where('due_date', '<', $today)->count(),
            'overdue' => $open()->where('due_date', '<', $today)->count(),
        ];
    }

    /**
     * Tasks of boards the user can see, optionally within an organization subtree and one board.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Task>
     */
    public function scoped(User $user, array $filters): Builder
    {
        return Task::query()->visibleTo($user)
            ->when(! empty($filters['organization']), fn ($q) => $q->whereIn('organization_id', OrganizationSubtree::ids((int) $filters['organization']) ?: [0]))
            ->when(! empty($filters['board']), fn ($q) => $q->where('board_id', (int) $filters['board']));
    }

    public function today(): string
    {
        return CarbonImmutable::today((string) config('app.display_timezone'))->toDateString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<string, array{open: int, overdue: int, due_week: int, done_30: int}>
     */
    private function counts(User $user, array $filters, string $column): Collection
    {
        $today = $this->today();
        $week = CarbonImmutable::parse($today)->addDays(7)->toDateString();
        $since = CarbonImmutable::now()->subDays(30);

        return $this->scoped($user, $filters)->toBase()
            ->select(DB::raw("COALESCE({$column}, '') AS group_key"))
            ->selectRaw('SUM(completed_at IS NULL) AS open')
            ->selectRaw('SUM(completed_at IS NULL AND due_date < ?) AS overdue', [$today])
            ->selectRaw('SUM(completed_at IS NULL AND due_date BETWEEN ? AND ?) AS due_week', [$today, $week])
            ->selectRaw('SUM(completed_at IS NOT NULL AND completed_at >= ?) AS done_30', [$since])
            ->groupBy('group_key')->get()
            ->filter(fn (object $r) => (int) $r->open + (int) $r->done_30 > 0)
            ->mapWithKeys(fn (object $r) => [(string) $r->group_key => [
                'open' => (int) $r->open, 'overdue' => (int) $r->overdue, 'due_week' => (int) $r->due_week, 'done_30' => (int) $r->done_30,
            ]]);
    }
}
