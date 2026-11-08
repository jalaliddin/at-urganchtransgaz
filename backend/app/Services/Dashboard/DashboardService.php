<?php

namespace App\Services\Dashboard;

use App\Enums\AntivirusEffective;
use App\Enums\EmployeeStatus;
use App\Enums\RequisitionStatus;
use App\Enums\TicketStatus;
use App\Http\Resources\Audit\AuditLogResource;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Requisition;
use App\Models\SoftwareLicense;
use App\Models\StockDocument;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Assets\AssetStats;
use App\Services\Helpdesk\TicketStats;
use App\Services\Requisitions\RequisitionQueries;
use App\Services\Software\ComplianceService;
use App\Services\Warehouse\StockQueries;
use App\Support\OrganizationSubtree;
use Carbon\CarbonImmutable;
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

        if ($user->can('helpdesk.view')) {
            $widgets['helpdesk'] = $this->helpdesk($user, $organizationId);
        } elseif ($user->can('helpdesk.create')) {
            $mine = Ticket::query()->visibleTo($user);
            $widgets['my_tickets'] = [
                'open' => (clone $mine)->whereIn('status', TicketStatus::openValues())->count(),
                'total' => $mine->count(),
            ];
        }

        if ($user->can('assets.view')) {
            $filters = $organizationId !== null ? ['organization' => $organizationId] : [];
            $assets = app(AssetStats::class)->summary($user, $filters);
            $widgets['assets'] = [
                'active' => $assets['active'],
                'in_repair' => $assets['by_status']['in_repair'],
                'warranty_expiring' => $assets['warranty_expiring'],
                'by_status' => $assets['by_status'],
            ];
        } elseif ($user->employee_id !== null) {
            $widgets['my_assets'] = ['total' => Asset::query()->withoutGlobalScopes()->where('holder_employee_id', $user->employee_id)->count()];
        }

        if ($user->can('warehouse.view')) {
            $stock = app(StockQueries::class);
            $monthStart = CarbonImmutable::now((string) config('app.display_timezone'))->startOfMonth()->toDateString();
            $documents = StockDocument::query()->visibleTo($user)->where('document_date', '>=', $monthStart);
            if ($organizationId !== null) {
                $ids = OrganizationSubtree::ids($organizationId);
                $documents->where(fn ($q) => $q->whereIn('organization_id', $ids)->orWhereIn('target_organization_id', $ids));
            }
            $widgets['warehouse'] = [
                'low_stock' => $stock->lowCount($user, $organizationId),
                'expiring' => $stock->expiringLots($user, $organizationId),
                'documents_month' => $documents->count(),
            ];
        }

        if ($user->canAny(['requisitions.view', 'requisitions.create'])) {
            $visible = fn () => Requisition::query()->visibleTo($user)
                ->when($organizationId !== null, fn ($q) => $q->whereIn('organization_id', OrganizationSubtree::ids((int) $organizationId) ?: [0]));
            $action = $visible();
            RequisitionQueries::awaitingAction($action, $user);
            $widgets['requisitions'] = [
                'awaiting_action' => $action->count(),
                'open' => $visible()->whereNotIn('status', [RequisitionStatus::Draft->value, RequisitionStatus::Rejected->value, RequisitionStatus::Closed->value])->count(),
                'mine_open' => $visible()->where('requester_id', $user->id)->whereNotIn('status', [RequisitionStatus::Rejected->value, RequisitionStatus::Closed->value])->count(),
            ];
        }

        if ($user->canAny(['software.view', 'software.install', 'software.manage'])) {
            $devices = app(ComplianceService::class)->devices($user, $organizationId !== null ? ['organization' => $organizationId] : []);
            $until = CarbonImmutable::now((string) config('app.display_timezone'))->addDays((int) config('software.license_notice_days'))->toDateString();
            $widgets['software'] = [
                'devices' => $devices->count(),
                'protected' => $devices->filter(fn (array $d) => $d['antivirus']->isProtected())->count(),
                'unprotected' => $devices->reject(fn (array $d) => $d['antivirus']->isProtected())->count(),
                'outdated' => $devices->filter(fn (array $d) => $d['antivirus'] === AntivirusEffective::Outdated)->count(),
                'missing_required' => $devices->filter(fn (array $d) => $d['missing'] !== [])->count(),
                'licenses_expiring' => SoftwareLicense::query()->whereNotNull('expires_at')->where('expires_at', '<=', $until)
                    ->when($organizationId !== null, fn ($q) => $q->whereIn('organization_id', OrganizationSubtree::ids((int) $organizationId) ?: [0]))->count(),
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

    /** @return array<string, mixed> */
    private function helpdesk(User $user, ?int $organizationId): array
    {
        $filters = $organizationId !== null ? ['organization' => $organizationId] : [];
        $summary = app(TicketStats::class);
        $base = fn () => $summary->base($user, $filters);
        $today = CarbonImmutable::now((string) config('app.display_timezone'))->startOfDay()->utc();

        $byStatus = [];
        $counts = $base()->select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status');
        foreach (TicketStatus::cases() as $status) {
            $byStatus[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return [
            'open' => $base()->whereIn('status', TicketStatus::openValues())->count(),
            'overdue' => $base()->whereIn('status', TicketStatus::openValues())->whereNull('paused_at')
                ->where('resolution_due_at', '<', now())->count(),
            'new_today' => $base()->where('created_at', '>=', $today)->count(),
            'assigned_to_me' => $base()->where('assignee_id', $user->id)->whereIn('status', TicketStatus::openValues())->count(),
            'by_status' => $byStatus,
        ];
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
