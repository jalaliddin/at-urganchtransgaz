<?php

namespace App\Services\Assets;

use App\Enums\AssetEventAction;
use App\Enums\AssetStatus;
use App\Enums\EmployeeStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Handing assets to employees and taking them back (spec §5.3/§5.4). */
class AssetAssignments
{
    public function __construct(
        private readonly AssetService $assets,
        private readonly AssetEventRecorder $events,
    ) {}

    public function assign(Asset $asset, Employee $employee, User $by, ?string $note): Asset
    {
        $this->assets->assertEditable($asset);
        if (in_array($asset->status, [AssetStatus::InRepair, AssetStatus::Lost], true)) {
            throw new InvalidTransitionException(__('messages.asset_not_assignable'));
        }
        if ($employee->organization_id !== $asset->organization_id || $employee->status === EmployeeStatus::Dismissed) {
            throw ValidationException::withMessages(['employee_id' => __('messages.asset_employee_invalid')]);
        }

        return DB::transaction(function () use ($asset, $employee, $by, $note): Asset {
            $this->closeOpen($asset, $by, __('messages.asset_reassigned'));
            AssetAssignment::create([
                'asset_id' => $asset->id, 'organization_id' => $asset->organization_id, 'employee_id' => $employee->id,
                'assigned_at' => CarbonImmutable::now(), 'assigned_by' => $by->id, 'note' => $note,
            ]);
            $old = ['holder_employee_id' => $asset->holder_employee_id, 'status' => $asset->status->value];
            $asset->forceFill(['holder_employee_id' => $employee->id, 'status' => AssetStatus::InUse])->save();
            $this->events->record($asset, $by, AssetEventAction::Assigned, $old, [
                'holder_employee_id' => $employee->id, 'holder' => $employee->full_name, 'status' => AssetStatus::InUse->value,
            ], $note);

            return $asset;
        });
    }

    public function return(Asset $asset, User $by, ?string $note): Asset
    {
        if ($asset->holder_employee_id === null) {
            throw new InvalidTransitionException(__('messages.asset_not_held'));
        }

        return DB::transaction(function () use ($asset, $by, $note): Asset {
            $holder = $asset->holder?->full_name;
            $this->closeOpen($asset, $by, $note);
            $asset->forceFill(['holder_employee_id' => null, 'status' => AssetStatus::InStock])->save();
            $this->events->record($asset, $by, AssetEventAction::Returned, ['holder' => $holder], ['status' => AssetStatus::InStock->value], $note);

            return $asset;
        });
    }

    /** Ends the current assignment (also used by transfer and write-off). */
    public function closeOpen(Asset $asset, ?User $by, ?string $note): void
    {
        AssetAssignment::query()->where('asset_id', $asset->id)->whereNull('returned_at')->update([
            'returned_at' => CarbonImmutable::now(), 'returned_by' => $by?->id, 'return_note' => $note,
        ]);
    }
}
