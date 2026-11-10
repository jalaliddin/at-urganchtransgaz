<?php

namespace App\Services\Changes;

use App\Enums\AssetEventAction;
use App\Enums\ChangeEvent;
use App\Enums\ChangeRisk;
use App\Enums\ChangeStatus;
use App\Enums\ChangeType;
use App\Exceptions\InvalidTransitionException;
use App\Models\Asset;
use App\Models\Change;
use App\Models\ChangeHistory;
use App\Models\Problem;
use App\Models\User;
use App\Notifications\Itsm\ChangeNotification;
use App\Services\Assets\AssetEventRecorder;
use App\Services\Helpdesk\HelpdeskRecipients;
use App\Services\Support\NumberSequence;
use App\Support\OrganizationSubtree;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/** Change management (design C1–C6). Every step re-reads the change under a row lock. */
class ChangeService
{
    private const PLAN_FIELDS = ['title', 'description', 'type', 'category', 'risk', 'impact', 'plan', 'backout_plan', 'planned_start', 'planned_end', 'problem_id'];

    public function __construct(
        private readonly NumberSequence $numbers,
        private readonly HelpdeskRecipients $recipients,
        private readonly AssetEventRecorder $events,
    ) {}

    /** @param  array<string, mixed>  $data  validated plan fields + organization_id, implementer_id?, asset_ids? */
    public function create(array $data, User $by): Change
    {
        $change = DB::transaction(function () use ($data, $by): Change {
            $change = new Change(['organization_id' => $data['organization_id'], ...$this->planFields($data)]);
            $year = CarbonImmutable::now((string) config('app.display_timezone'))->year;
            $change->forceFill([
                'number' => sprintf('CHG-%d-%05d', $year, $this->numbers->next("change:{$year}")),
                'status' => ChangeStatus::Draft,
                'implementer_id' => $this->implementer($change->organization_id, $data['implementer_id'] ?? null),
                'created_by' => $by->id,
            ])->save();
            $this->syncAssets($change, (array) ($data['asset_ids'] ?? []), $by);
            $this->record($change, ChangeEvent::Created, $by);

            return $change;
        });
        $this->notifyImplementer($change, null, $by);

        return $change;
    }

    /** @param  array<string, mixed>  $data  Only drafts change (design C2). */
    public function update(Change $change, array $data, User $by): Change
    {
        $before = $change->implementer_id;
        $change = $this->locked($change, function (Change $c) use ($data, $by): void {
            if ($c->status !== ChangeStatus::Draft) {
                throw new InvalidTransitionException(__('messages.change_locked'));
            }
            $c->fill($this->planFields($data));
            if (array_key_exists('implementer_id', $data)) {
                $c->implementer_id = $this->implementer($c->organization_id, $data['implementer_id']);
            }
            $fields = array_values(array_diff(array_keys($c->getDirty()), ['updated_by']));
            $c->save();
            if (array_key_exists('asset_ids', $data)) {
                $fields = [...$fields, ...($this->syncAssets($c, (array) $data['asset_ids'], $by) ? ['assets'] : [])];
            }
            if ($fields !== []) {
                $this->record($c, ChangeEvent::Updated, $by, ['fields' => $fields]);
            }
        });
        $this->notifyImplementer($change, $before, $by);

        return $change;
    }

    public function submit(Change $change, User $by): Change
    {
        $change = $this->locked($change, function (Change $c) use ($by): void {
            $this->expect($c, [ChangeStatus::Draft]);
            $this->assertReady($c);
            $now = CarbonImmutable::now();
            $c->submitted_at = $now;
            $c->status = $c->type === ChangeType::Normal ? ChangeStatus::PendingApproval : ChangeStatus::Approved;
            $c->save();
            $this->record($c, ChangeEvent::Submitted, $by, ['type' => $c->type->value]);
        });
        if ($change->type !== ChangeType::Standard) {
            $approvers = $this->recipients->withPermission('changes.approve', $change->organization_id)->reject(fn (User $u) => $u->id === $by->id);
            Notification::send($approvers, new ChangeNotification($change->type === ChangeType::Emergency ? 'emergency' : 'approval',
                $change->id, $change->number, $change->title, $by->name, $this->when($change->planned_start)));
        }

        return $change;
    }

    public function decide(Change $change, bool $approve, ?string $comment, User $by): Change
    {
        $change = $this->locked($change, function (Change $c) use ($approve, $comment, $by): void {
            $this->expect($c, [ChangeStatus::PendingApproval]);
            if ($c->created_by === $by->id) {
                throw new InvalidTransitionException(__('messages.change_self_approval'));
            }
            if (! $approve && blank($comment)) {
                throw ValidationException::withMessages(['comment' => __('messages.change_reject_reason')]);
            }
            $c->forceFill([
                'status' => $approve ? ChangeStatus::Approved : ChangeStatus::Rejected,
                'decided_by' => $by->id, 'decided_at' => CarbonImmutable::now(), 'decision_comment' => $comment,
            ])->save();
            $this->record($c, $approve ? ChangeEvent::Approved : ChangeEvent::Rejected, $by, null, $comment);
        });
        $notify = User::query()->withoutGlobalScopes()->where('is_active', true)->where('id', '!=', $by->id)
            ->whereIn('id', array_filter([$change->created_by, $change->implementer_id]))->get();
        Notification::send($notify, new ChangeNotification($approve ? 'approved' : 'rejected', $change->id, $change->number, $change->title, $by->name));

        return $change;
    }

    public function start(Change $change, User $by): Change
    {
        return $this->locked($change, function (Change $c) use ($by): void {
            $this->expect($c, [ChangeStatus::Approved]);
            $c->forceFill(['status' => ChangeStatus::InProgress, 'actual_start' => CarbonImmutable::now()])->save();
            $this->record($c, ChangeEvent::Started, $by);
        });
    }

    public function complete(Change $change, bool $success, string $result, User $by): Change
    {
        return $this->locked($change, function (Change $c) use ($success, $result, $by): void {
            $this->expect($c, [ChangeStatus::InProgress]);
            $status = $success ? ChangeStatus::Implemented : ChangeStatus::Failed;
            $c->forceFill(['status' => $status, 'actual_end' => CarbonImmutable::now(), 'result' => $result])->save();
            $this->record($c, $success ? ChangeEvent::Implemented : ChangeEvent::Failed, $by, null, $result);
            foreach ($c->assets()->get() as $asset) {
                $this->events->record($asset, $by, AssetEventAction::ChangeImplemented, null,
                    ['number' => $c->number, 'title' => $c->title, 'status' => $status->value], $result);
            }
        });
    }

    public function cancel(Change $change, string $reason, User $by): Change
    {
        return $this->locked($change, function (Change $c) use ($reason, $by): void {
            $this->expect($c, [ChangeStatus::Draft, ChangeStatus::PendingApproval, ChangeStatus::Approved]);
            $c->forceFill(['status' => ChangeStatus::Cancelled])->save();
            $this->record($c, ChangeEvent::Cancelled, $by, null, $reason);
        });
    }

    public function revise(Change $change, User $by): Change
    {
        return $this->locked($change, function (Change $c) use ($by): void {
            $this->expect($c, [ChangeStatus::Rejected]);
            $c->forceFill(['status' => ChangeStatus::Draft, 'submitted_at' => null])->save();
            $this->record($c, ChangeEvent::Revised, $by);
        });
    }

    public function comment(Change $change, string $comment, User $by): void
    {
        $this->record($change, ChangeEvent::Commented, $by, null, $comment);
    }

    /**
     * Approved or running changes the user can see that share an affected asset and whose planned window overlaps
     * (design C4).
     *
     * @return Collection<int, Change>
     */
    public function conflicts(Change $change, User $user): Collection
    {
        if ($change->planned_start === null || $change->planned_end === null || $change->status->isFinished()) {
            return new Collection;
        }

        return Change::query()->visibleTo($user)->where('changes.id', '!=', $change->id)
            ->whereIn('status', [ChangeStatus::Approved->value, ChangeStatus::InProgress->value])
            ->where('planned_start', '<', $change->planned_end)->where('planned_end', '>', $change->planned_start)
            ->whereIn('changes.id', DB::table('change_assets')->select('change_id')
                ->whereIn('asset_id', DB::table('change_assets')->select('asset_id')->where('change_id', $change->id)))
            ->orderBy('planned_start')->get();
    }

    /** Implementer: an active user who works on changes and can open the organization. */
    public function implementer(int $organizationId, mixed $userId): ?int
    {
        if ($userId === null || $userId === '') {
            return null;
        }
        $user = User::query()->withoutGlobalScopes()->where('is_active', true)->find((int) $userId);
        if ($user === null || ! $user->canAny(['changes.create', 'changes.manage']) || ! $user->canAccessOrganization($organizationId)) {
            throw ValidationException::withMessages(['implementer_id' => __('messages.change_implementer_invalid')]);
        }

        return $user->id;
    }

    /** The creator (while they may create changes) or changes.manage. */
    public function canEdit(Change $change, User $user): bool
    {
        return $user->can('changes.manage') || ($user->can('changes.create') && $change->created_by === $user->id);
    }

    /** The implementer or changes.manage. */
    public function canRun(Change $change, User $user): bool
    {
        return $user->can('changes.manage') || $change->implementer_id === $user->id;
    }

    /** A problem the change fixes must be one the user can see. */
    public function problemVisible(User $user, int $problemId): bool
    {
        return Problem::query()->visibleTo($user)->whereKey($problemId)->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function planFields(array $data): array
    {
        $fields = array_intersect_key($data, array_flip(self::PLAN_FIELDS));
        foreach (['planned_start', 'planned_end'] as $key) {
            if (array_key_exists($key, $fields)) {
                $fields[$key] = $fields[$key] === null ? null
                    : CarbonImmutable::parse((string) $fields[$key], (string) config('app.display_timezone'))->utc();
            }
        }

        return $fields;
    }

    /**
     * Assets of the change organization's subtree only (design C1). Returns whether the set changed.
     *
     * @param  array<int, mixed>  $assetIds
     */
    private function syncAssets(Change $change, array $assetIds, User $by): bool
    {
        $ids = array_values(array_unique(array_map('intval', $assetIds)));
        $allowed = Asset::query()->visibleTo($by)->whereIn('assets.id', $ids)
            ->whereIn('assets.organization_id', OrganizationSubtree::ids($change->organization_id) ?: [0])->count();
        if ($allowed !== count($ids)) {
            throw ValidationException::withMessages(['asset_ids' => __('messages.change_asset_invalid')]);
        }
        $result = $change->assets()->sync($ids);

        return $result['attached'] !== [] || $result['detached'] !== [];
    }

    /** Submission needs the plan, the window and (medium/high risk) a way back. */
    private function assertReady(Change $c): void
    {
        $errors = [];
        if (blank($c->plan)) {
            $errors['plan'] = __('messages.change_field_required');
        }
        if ($c->planned_start === null) {
            $errors['planned_start'] = __('messages.change_field_required');
        }
        if ($c->planned_end === null || ($c->planned_start !== null && $c->planned_end <= $c->planned_start)) {
            $errors['planned_end'] = __('messages.change_window_invalid');
        }
        if ($c->risk !== ChangeRisk::Low && $c->type !== ChangeType::Standard && blank($c->backout_plan)) {
            $errors['backout_plan'] = __('messages.change_field_required');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param  list<ChangeStatus>  $allowed */
    private function expect(Change $c, array $allowed): void
    {
        if (! in_array($c->status, $allowed, true)) {
            throw new InvalidTransitionException(__('messages.change_wrong_status', ['status' => $c->status->label()]));
        }
    }

    /** @param  Closure(Change): void  $work */
    private function locked(Change $change, Closure $work): Change
    {
        return DB::transaction(function () use ($change, $work): Change {
            $locked = Change::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($change->id);
            $work($locked);

            return $locked;
        });
    }

    /** @param  array<string, mixed>|null  $payload */
    private function record(Change $change, ChangeEvent $event, User $by, ?array $payload = null, ?string $comment = null): void
    {
        ChangeHistory::query()->create(['change_id' => $change->id, 'event' => $event, 'payload' => $payload, 'comment' => $comment, 'user_id' => $by->id]);
    }

    private function notifyImplementer(Change $change, ?int $before, User $by): void
    {
        if ($change->implementer_id !== null && $change->implementer_id !== $before && $change->implementer_id !== $by->id) {
            User::query()->withoutGlobalScopes()->find($change->implementer_id)
                ?->notify(new ChangeNotification('assigned', $change->id, $change->number, $change->title, $by->name, $this->when($change->planned_start)));
        }
    }

    private function when(?CarbonImmutable $at): string
    {
        return $at === null ? '' : $at->setTimezone((string) config('app.display_timezone'))->format('d.m.Y H:i');
    }
}
