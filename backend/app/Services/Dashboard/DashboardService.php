<?php

namespace App\Services\Dashboard;

use App\Enums\EmployeeStatus;
use App\Http\Resources\Audit\AuditLogResource;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1 dashboard metrics. A widget is present only when the user holds its
 * permission; every count is limited by the user's scope (and the optional subtree).
 */
class DashboardService
{
    /** @return array<string, mixed> */
    public function forUser(User $user, ?int $organizationId): array
    {
        $widgets = ['profile' => $this->profile($user)];

        if ($user->can('organizations.view')) {
            $organizations = $this->within(Organization::query(), $organizationId);
            $widgets['organizations'] = [
                'total' => (clone $organizations)->count(),
                'active' => (clone $organizations)->where('is_active', true)->count(),
            ];

            $active = (clone $organizations)->where('is_active', true);
            $with = (clone $active)->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('organization_it_responsibles')->whereColumn('organization_it_responsibles.organization_id', 'organizations.id'))->count();
            $widgets['it_coverage'] = ['with_responsible' => $with, 'without_responsible' => $active->count() - $with];
        }

        if ($user->can('employees.view')) {
            $counts = $this->within(Employee::query(), $organizationId)
                ->select('status', DB::raw('count(*) as aggregate'))->groupBy('status')
                ->pluck('aggregate', 'status');
            $byStatus = [];
            foreach (EmployeeStatus::cases() as $status) {
                $byStatus[$status->value] = (int) ($counts[$status->value] ?? 0);
            }
            $widgets['employees'] = ['total' => array_sum($byStatus), 'by_status' => $byStatus];
        }

        if ($user->can('users.view')) {
            $users = $this->within(User::query(), $organizationId);
            $widgets['users'] = [
                'total' => (clone $users)->count(),
                'active' => (clone $users)->where('is_active', true)->count(),
                'pending_password_change' => (clone $users)->where('must_change_password', true)->count(),
            ];
        }

        if ($user->can('audit.view')) {
            $logs = AuditLog::query()->visibleTo($user)->with(AuditLogResource::RELATIONS)->latest('created_at')->latest('id')->limit(10);
            if ($organizationId !== null) {
                $path = (string) DB::table('organizations')->where('id', $organizationId)->value('path');
                $logs->whereIn('organization_id', DB::table('organizations')->select('id')->where('path', 'like', $path.'%'));
            }
            $widgets['recent_audit'] = AuditLogResource::collection($logs->get())->resolve(request());
        }

        return $widgets;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function within(Builder $query, ?int $organizationId): Builder
    {
        return $organizationId === null ? $query : $query->withinOrganizationSubtree($organizationId); // @phpstan-ignore method.notFound
    }

    /** @return array<string, mixed> */
    private function profile(User $user): array
    {
        $user->loadMissing(['organization', 'roles']);

        return [
            'name' => $user->name,
            'username' => $user->username,
            'organization' => $user->organization ? ['id' => $user->organization->id, 'name' => $user->organization->name] : null,
            'roles' => $user->roles->map(fn ($role): string => (string) ($role->getAttribute('display_name') ?: $role->getAttribute('name')))->values()->all(),
        ];
    }
}
