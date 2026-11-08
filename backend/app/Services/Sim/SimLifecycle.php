<?php

namespace App\Services\Sim;

use App\Enums\EmployeeStatus;
use App\Enums\SimEvent;
use App\Enums\SimStatus as S;
use App\Exceptions\InvalidTransitionException;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\SimAssignment;
use App\Models\SimCard;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Status changes of a SIM card. Each one re-reads the card under a row lock, checks the status there, writes the
 * assignment period and a history row. Invalid transitions → 422 invalid_transition.
 */
class SimLifecycle
{
    public function __construct(private readonly SimCards $cards) {}

    /** @param  array{employee_id?: int|null, department_id?: int|null, assigned_at?: string|null, note?: string|null}  $data */
    public function assign(SimCard $card, array $data, User $by): SimCard
    {
        return $this->change($card, function (SimCard $c) use ($data, $by): void {
            $this->expect($c, $c->status->isAvailable());
            $employee = empty($data['employee_id']) ? null
                : Employee::query()->withoutGlobalScopes()->whereKey((int) $data['employee_id'])->where('organization_id', $c->organization_id)->first();
            $department = empty($data['department_id']) ? null
                : Department::query()->withoutGlobalScopes()->whereKey((int) $data['department_id'])->where('organization_id', $c->organization_id)->first();
            if (empty($data['employee_id']) && empty($data['department_id'])) {
                throw ValidationException::withMessages(['employee_id' => __('messages.sim_holder_required')]);
            }
            if (! empty($data['employee_id']) && ($employee === null || $employee->status === EmployeeStatus::Dismissed)) {
                throw ValidationException::withMessages(['employee_id' => __('messages.sim_employee_other_organization')]);
            }
            if (! empty($data['department_id']) && $department === null) {
                throw ValidationException::withMessages(['department_id' => __('messages.sim_department_other_organization')]);
            }
            $date = $this->date($data['assigned_at'] ?? null);
            $c->forceFill(['employee_id' => $employee?->id, 'department_id' => $department->id ?? $employee?->department_id,
                'status' => S::Active, 'previous_status' => null, 'allocated_at' => $date, 'returned_at' => null])->save();
            SimAssignment::query()->create(['sim_card_id' => $c->id, 'organization_id' => $c->organization_id, 'employee_id' => $c->employee_id,
                'department_id' => $c->department_id, 'assigned_at' => $date, 'assigned_by' => $by->id, 'note' => $data['note'] ?? null]);
            $this->cards->record($c, SimEvent::Assigned, $by, array_filter([
                'employee' => $employee?->full_name, 'department' => $c->department_id ? Department::query()->withoutGlobalScopes()->whereKey($c->department_id)->value('name') : null,
                'date' => $date->toDateString(), 'note' => $data['note'] ?? null,
            ], fn ($v) => $v !== null));
        });
    }

    /** From an employee/department back to the holder organization. */
    public function return(SimCard $card, ?string $date, ?string $note, User $by): SimCard
    {
        return $this->change($card, function (SimCard $c) use ($date, $note, $by): void {
            $open = $c->openAssignment()->lockForUpdate()->first();
            $this->expect($c, $open !== null && in_array($c->status, [S::Active, S::Blocked], true));
            $day = $this->date($date);
            $open->forceFill(['returned_at' => $day, 'returned_by' => $by->id])->save();
            $c->forceFill(['employee_id' => null, 'department_id' => null, 'status' => S::Returned, 'previous_status' => null, 'returned_at' => $day])->save();
            $this->cards->record($c, SimEvent::Returned, $by, array_filter(['date' => $day->toDateString(), 'note' => $note], fn ($v) => $v !== null));
        });
    }

    public function block(SimCard $card, ?string $reason, User $by): SimCard
    {
        return $this->change($card, function (SimCard $c) use ($reason, $by): void {
            $this->expect($c, in_array($c->status, [S::InStock, S::Active, S::Returned], true));
            $c->forceFill(['previous_status' => $c->status, 'status' => S::Blocked])->save();
            $this->cards->record($c, SimEvent::Blocked, $by, array_filter(['reason' => $reason], fn ($v) => $v !== null));
        });
    }

    public function unblock(SimCard $card, User $by): SimCard
    {
        return $this->change($card, function (SimCard $c) use ($by): void {
            $this->expect($c, $c->status === S::Blocked);
            $back = $c->previous_status ?? ($c->openAssignment()->exists() ? S::Active : S::InStock);
            $c->forceFill(['status' => $back, 'previous_status' => null])->save();
            $this->cards->record($c, SimEvent::Unblocked, $by, ['status' => $back->value]);
        });
    }

    /** Moves a free card to another organization of the user's scope (checked by the caller). */
    public function transfer(SimCard $card, int $organizationId, ?string $note, User $by): SimCard
    {
        return $this->change($card, function (SimCard $c) use ($organizationId, $note, $by): void {
            $this->expect($c, $c->status->isAvailable());
            if ($c->organization_id === $organizationId) {
                throw ValidationException::withMessages(['organization_id' => __('messages.sim_same_organization')]);
            }
            $from = $c->organization_id;
            $c->forceFill(['organization_id' => $organizationId, 'employee_id' => null, 'department_id' => null])->save();
            $this->cards->record($c, SimEvent::Transferred, $by, array_filter(['from' => $from, 'to' => $organizationId, 'note' => $note], fn ($v) => $v !== null));
        });
    }

    /**
     * Requisition fulfilment: a free card that is not promised to another open requisition goes to the
     * requisition's organization (caller holds the requisition lock and has checked visibility).
     */
    public function allocate(SimCard $card, Requisition $requisition, User $by): SimCard
    {
        return $this->change($card, function (SimCard $c) use ($requisition, $by): void {
            if (! $c->status->isAvailable()) {
                throw ValidationException::withMessages(['sim_card_id' => __('messages.allocation_sim_unavailable')]);
            }
            $from = $c->organization_id;
            $c->forceFill(['organization_id' => $requisition->organization_id, 'employee_id' => null, 'department_id' => null])->save();
            $this->cards->record($c, SimEvent::Allocated, $by, ['from' => $from, 'to' => $requisition->organization_id, 'requisition' => $requisition->number], $requisition->id);
        });
    }

    public function cancel(SimCard $card, ?string $reason, User $by): SimCard
    {
        return $this->change($card, function (SimCard $c) use ($reason, $by): void {
            $this->expect($c, $c->status !== S::Cancelled);
            $today = $this->date(null);
            $c->openAssignment()->lockForUpdate()->first()?->forceFill(['returned_at' => $today, 'returned_by' => $by->id])->save();
            $c->forceFill(['employee_id' => null, 'department_id' => null, 'status' => S::Cancelled, 'previous_status' => null, 'returned_at' => $c->returned_at ?? $today])->save();
            $this->cards->record($c, SimEvent::Cancelled, $by, array_filter(['reason' => $reason], fn ($v) => $v !== null));
        });
    }

    /** New physical card for the same number (lost or damaged). */
    public function replace(SimCard $card, string $iccid, ?string $reason, User $by): SimCard
    {
        return $this->change($card, function (SimCard $c) use ($iccid, $reason, $by): void {
            $this->expect($c, $c->status !== S::Cancelled);
            $old = $c->iccid_last4;
            $this->cards->applyNumbers($c, ['iccid' => $iccid]);
            if ($c->iccid_last4 === null) {
                throw ValidationException::withMessages(['iccid' => __('messages.sim_iccid_invalid')]);
            }
            $c->save();
            $this->cards->record($c, SimEvent::Replaced, $by, array_filter(['old_last4' => $old, 'new_last4' => $c->iccid_last4, 'reason' => $reason], fn ($v) => $v !== null));
        });
    }

    /** @param  callable(SimCard): void  $work */
    private function change(SimCard $card, callable $work): SimCard
    {
        return DB::transaction(function () use ($card, $work): SimCard {
            $locked = SimCard::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($card->id);
            $work($locked);

            return $locked;
        }, 3);
    }

    private function expect(SimCard $card, bool $allowed): void
    {
        if (! $allowed) {
            throw new InvalidTransitionException(__('messages.sim_invalid_transition', ['status' => $card->status->label()]));
        }
    }

    private function date(?string $value): CarbonImmutable
    {
        $tz = (string) config('app.display_timezone');

        return $value === null || $value === '' ? CarbonImmutable::today($tz) : CarbonImmutable::parse($value, $tz)->startOfDay();
    }
}
