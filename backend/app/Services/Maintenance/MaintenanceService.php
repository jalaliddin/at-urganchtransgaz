<?php

namespace App\Services\Maintenance;

use App\Enums\AssetEventAction;
use App\Enums\AssetStatus;
use App\Enums\MaintenanceResult;
use App\Enums\MaintenanceTaskStatus;
use App\Exceptions\DomainConflictException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Asset;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceTask;
use App\Models\User;
use App\Notifications\Itsm\MaintenanceNotification;
use App\Services\Assets\AssetEventRecorder;
use App\Services\Helpdesk\HelpdeskRecipients;
use App\Support\OrganizationSubtree;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/** Preventive maintenance plans and their work items (design M1–M5). */
class MaintenanceService
{
    public function __construct(
        private readonly MaintenanceSchedule $schedule,
        private readonly AssetEventRecorder $events,
        private readonly HelpdeskRecipients $recipients,
    ) {}

    /** @param  array<string, mixed>  $data  validated plan fields + asset_ids? */
    public function save(?MaintenancePlan $plan, array $data, User $by): MaintenancePlan
    {
        $plan = DB::transaction(function () use ($plan, $data, $by): MaintenancePlan {
            $plan = $plan === null ? new MaintenancePlan(['organization_id' => $data['organization_id']])
                : MaintenancePlan::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($plan->id);
            $plan->fill(array_intersect_key($data, array_flip(['title', 'description', 'checklist', 'interval_count', 'interval_unit', 'start_on', 'lead_days', 'is_active'])));
            if (array_key_exists('checklist', $data)) {
                $plan->checklist = array_values(array_filter(array_map(fn ($s) => trim((string) $s), (array) $data['checklist']), fn ($s) => $s !== ''));
            }
            if (array_key_exists('assignee_id', $data) || ! $plan->exists) {
                $plan->assignee_id = $this->assignee($plan->organization_id, $data['assignee_id'] ?? null);
            }
            $plan->save();
            $plan->refresh();
            if (array_key_exists('asset_ids', $data)) {
                $plan->assets()->sync($this->assetIds($plan, (array) $data['asset_ids'], $by));
            }

            return $plan;
        });
        $this->generate($plan, $this->today());

        return $plan;
    }

    /** Only plans without finished work disappear; the rest are deactivated (history stays). */
    public function delete(MaintenancePlan $plan): void
    {
        DB::transaction(function () use ($plan): void {
            $locked = MaintenancePlan::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($plan->id);
            if (MaintenanceTask::query()->withoutGlobalScopes()->where('plan_id', $locked->id)
                ->whereIn('status', [MaintenanceTaskStatus::Done->value, MaintenanceTaskStatus::Skipped->value])->exists()) {
                throw new DomainConflictException(__('messages.maintenance_plan_has_history'));
            }
            $locked->delete();
        });
    }

    /**
     * Keeps exactly one open item per device of an active plan; cancels items of removed or written-off devices and of
     * inactive plans. Safe to run any number of times (open_key and plan+asset+due UNIQUE).
     */
    public function generate(MaintenancePlan $plan, CarbonImmutable $today): void
    {
        $assets = $plan->is_active
            ? Asset::query()->withoutGlobalScopes()->whereIn('id', DB::table('maintenance_plan_assets')->select('asset_id')->where('plan_id', $plan->id))
                ->where('status', '!=', AssetStatus::WrittenOff->value)->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];
        MaintenanceTask::query()->withoutGlobalScopes()->where('plan_id', $plan->id)->where('status', MaintenanceTaskStatus::Planned->value)
            ->whereNotIn('asset_id', $assets ?: [0])
            ->update(['status' => MaintenanceTaskStatus::Cancelled->value, 'open_key' => null, 'updated_at' => CarbonImmutable::now()]);

        $open = MaintenanceTask::query()->withoutGlobalScopes()->where('plan_id', $plan->id)
            ->where('status', MaintenanceTaskStatus::Planned->value)->pluck('asset_id')->map(fn ($id) => (int) $id)->all();
        foreach (array_diff($assets, $open) as $assetId) {
            $this->schedule($plan, $assetId, $today);
        }
    }

    /** @param  array<string, mixed>  $data  result, checklist (one answer per step), note?, completed_on? */
    public function complete(MaintenanceTask $task, array $data, User $by): MaintenanceTask
    {
        return DB::transaction(function () use ($task, $data, $by): MaintenanceTask {
            $locked = $this->lockedPlanned($task);
            $steps = $locked->plan->checklist ?? [];
            $answers = array_values((array) ($data['checklist'] ?? []));
            if (count($answers) !== count($steps)) {
                throw ValidationException::withMessages(['checklist' => __('messages.maintenance_checklist_mismatch')]);
            }
            $result = MaintenanceResult::from((string) $data['result']);
            $locked->forceFill([
                'status' => MaintenanceTaskStatus::Done,
                'open_key' => null,
                'completed_on' => $data['completed_on'] ?? $this->today()->toDateString(),
                'completed_by' => $by->id,
                'result' => $result,
                'note' => $data['note'] ?? null,
                'checklist' => array_map(fn (string $step, array $a) => [
                    'step' => $step, 'done' => (bool) ($a['done'] ?? false), 'note' => filled($a['note'] ?? null) ? (string) $a['note'] : null,
                ], $steps, $answers),
            ])->save();
            $this->events->record($locked->asset, $by, AssetEventAction::PreventiveDone, null,
                ['plan' => $locked->plan->title, 'due_on' => $locked->due_on->toDateString(), 'result' => $result->value], $locked->note);
            $this->scheduleAfter($locked);

            return $locked;
        });
    }

    public function skip(MaintenanceTask $task, string $reason, User $by): MaintenanceTask
    {
        return DB::transaction(function () use ($task, $reason, $by): MaintenanceTask {
            $locked = $this->lockedPlanned($task);
            $locked->forceFill(['status' => MaintenanceTaskStatus::Skipped, 'open_key' => null, 'completed_by' => $by->id,
                'completed_on' => $this->today()->toDateString(), 'note' => $reason])->save();
            $this->scheduleAfter($locked);

            return $locked;
        });
    }

    /** Daily run (design M2): items for every active plan, then "due soon" and "overdue" reminders once per item. */
    public function daily(CarbonImmutable $today): void
    {
        MaintenancePlan::query()->withoutGlobalScopes()->orderBy('id')->each(fn (MaintenancePlan $p) => $this->generate($p, $today));

        $tasks = MaintenanceTask::query()->withoutGlobalScopes()->with(['plan', 'asset'])->where('status', MaintenanceTaskStatus::Planned->value)
            ->whereHas('plan', fn ($q) => $q->where('is_active', true))->orderBy('id')->get();
        foreach ($tasks as $task) {
            $type = $task->due_on->toDateString() < $today->toDateString() ? 'overdue'
                : ($task->due_on->subDays($task->plan->lead_days)->toDateString() <= $today->toDateString() ? 'due_soon' : null);
            if ($type === null || DB::table('maintenance_alerts')->insertOrIgnore(['task_id' => $task->id, 'type' => $type, 'created_at' => CarbonImmutable::now()]) === 0) {
                continue;
            }
            Notification::send($this->recipientsFor($task->plan), new MaintenanceNotification($type, $task->id, $task->plan->title,
                $task->asset->name, $task->due_on->format('d.m.Y')));
        }
    }

    public function today(): CarbonImmutable
    {
        return CarbonImmutable::today((string) config('app.display_timezone'));
    }

    /** Responsible person: an active user who performs maintenance in the organization (design M5). */
    public function assignee(int $organizationId, mixed $userId): ?int
    {
        if ($userId === null || $userId === '') {
            return null;
        }
        $user = User::query()->withoutGlobalScopes()->where('is_active', true)->find((int) $userId);
        if ($user === null || ! $user->can('maintenance.perform') || ! $user->canAccessOrganization($organizationId)) {
            throw ValidationException::withMessages(['assignee_id' => __('messages.maintenance_assignee_invalid')]);
        }

        return $user->id;
    }

    /**
     * The responsible person, or everyone who plans maintenance for the organization.
     *
     * @return Collection<int, User>
     */
    private function recipientsFor(MaintenancePlan $plan): Collection
    {
        $assignee = $plan->assignee_id === null ? null : User::query()->withoutGlobalScopes()->where('is_active', true)->find($plan->assignee_id);
        if ($assignee !== null && $assignee->can('maintenance.perform') && $assignee->canAccessOrganization($plan->organization_id)) {
            return collect([$assignee]);
        }

        return $this->recipients->withPermission('maintenance.manage', $plan->organization_id);
    }

    private function lockedPlanned(MaintenanceTask $task): MaintenanceTask
    {
        $locked = MaintenanceTask::query()->withoutGlobalScopes()->with(['plan', 'asset'])->lockForUpdate()->findOrFail($task->id);
        if ($locked->status !== MaintenanceTaskStatus::Planned) {
            throw new InvalidTransitionException(__('messages.maintenance_task_closed'));
        }

        return $locked;
    }

    /** The next item after a finished one, when the device is still served by an active plan. */
    private function scheduleAfter(MaintenanceTask $task): void
    {
        $plan = $task->plan;
        $served = $plan->is_active && $task->asset->status !== AssetStatus::WrittenOff
            && DB::table('maintenance_plan_assets')->where('plan_id', $plan->id)->where('asset_id', $task->asset_id)->exists();
        if ($served) {
            $this->schedule($plan, $task->asset_id, $this->today(), $task->due_on);
        }
    }

    private function schedule(MaintenancePlan $plan, int $assetId, CarbonImmutable $today, ?CarbonImmutable $after = null): void
    {
        if ($after === null) {
            $last = MaintenanceTask::query()->withoutGlobalScopes()->where('plan_id', $plan->id)->where('asset_id', $assetId)
                ->whereIn('status', [MaintenanceTaskStatus::Done->value, MaintenanceTaskStatus::Skipped->value])->max('due_on');
            $after = $last === null ? null : CarbonImmutable::parse((string) $last);
        }
        $due = $this->schedule->next($plan->start_on, $plan->interval_count, $plan->interval_unit, $after, $today);
        // A cancelled item of the same date (plan paused or device removed and back) is reopened, not duplicated.
        $reopened = MaintenanceTask::query()->withoutGlobalScopes()->where('plan_id', $plan->id)->where('asset_id', $assetId)
            ->where('due_on', $due->toDateString())->where('status', MaintenanceTaskStatus::Cancelled->value)
            ->update(['status' => MaintenanceTaskStatus::Planned->value, 'open_key' => "{$plan->id}:{$assetId}", 'updated_at' => CarbonImmutable::now()]);
        if ($reopened > 0) {
            return;
        }
        DB::table('maintenance_tasks')->insertOrIgnore([
            'plan_id' => $plan->id, 'organization_id' => $plan->organization_id, 'asset_id' => $assetId, 'due_on' => $due->toDateString(),
            'status' => MaintenanceTaskStatus::Planned->value, 'open_key' => "{$plan->id}:{$assetId}",
            'created_at' => CarbonImmutable::now(), 'updated_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * Devices of the plan organization's subtree the user can see (design M1).
     *
     * @param  array<int, mixed>  $assetIds
     * @return list<int>
     */
    private function assetIds(MaintenancePlan $plan, array $assetIds, User $by): array
    {
        $ids = array_values(array_unique(array_map('intval', $assetIds)));
        $allowed = Asset::query()->visibleTo($by)->whereIn('assets.id', $ids)
            ->whereIn('assets.organization_id', OrganizationSubtree::ids($plan->organization_id) ?: [0])->count();
        if ($allowed !== count($ids)) {
            throw ValidationException::withMessages(['asset_ids' => __('messages.maintenance_asset_invalid')]);
        }

        return $ids;
    }
}
