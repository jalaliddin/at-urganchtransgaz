<?php

namespace App\Services\Assets;

use App\Enums\AssetEventAction;
use App\Enums\AssetStatus;
use App\Enums\DisposalStatus;
use App\Enums\MaintenanceStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Models\AssetMaintenance;
use App\Models\User;
use App\Notifications\Assets\AssetDisposalRequestedNotification;
use App\Services\Helpdesk\HelpdeskRecipients;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** Repairs (in_repair and back) and write-off with approval (→ written_off). */
class AssetLifecycle
{
    public function __construct(
        private readonly AssetService $assets,
        private readonly AssetAssignments $assignments,
        private readonly AssetEventRecorder $events,
        private readonly HelpdeskRecipients $recipients,
    ) {}

    public function openMaintenance(Asset $asset, string $description, ?string $vendor, ?int $ticketId, User $by): AssetMaintenance
    {
        $this->assets->assertEditable($asset);
        if (AssetMaintenance::query()->where('asset_id', $asset->id)->where('status', MaintenanceStatus::Open)->exists()) {
            throw new InvalidTransitionException(__('messages.asset_repair_open'));
        }

        return DB::transaction(function () use ($asset, $description, $vendor, $ticketId, $by): AssetMaintenance {
            $maintenance = AssetMaintenance::create([
                'asset_id' => $asset->id, 'ticket_id' => $ticketId, 'status' => MaintenanceStatus::Open,
                'started_at' => CarbonImmutable::now(), 'vendor' => $vendor, 'description' => $description, 'created_by' => $by->id,
            ]);
            $previous = $asset->status;
            $asset->forceFill(['previous_status' => $previous, 'status' => AssetStatus::InRepair])->save();
            $this->events->record($asset, $by, AssetEventAction::MaintenanceOpened, ['status' => $previous->value], ['status' => AssetStatus::InRepair->value], $description);

            return $maintenance;
        });
    }

    public function closeMaintenance(Asset $asset, AssetMaintenance $maintenance, string $result, ?string $cost, User $by): AssetMaintenance
    {
        if ($maintenance->status !== MaintenanceStatus::Open) {
            throw new InvalidTransitionException(__('messages.invalid_transition'));
        }

        return DB::transaction(function () use ($asset, $maintenance, $result, $cost, $by): AssetMaintenance {
            $maintenance->forceFill([
                'status' => MaintenanceStatus::Closed, 'finished_at' => CarbonImmutable::now(), 'result' => $result,
                'cost' => $cost, 'closed_by' => $by->id,
            ])->save();
            $restore = $asset->previous_status ?? ($asset->holder_employee_id ? AssetStatus::InUse : AssetStatus::InStock);
            if ($asset->status === AssetStatus::InRepair) {
                $asset->forceFill(['status' => $restore, 'previous_status' => null])->save();
            }
            $this->events->record($asset, $by, AssetEventAction::MaintenanceClosed, ['status' => AssetStatus::InRepair->value], ['status' => $asset->status->value], $result);

            return $maintenance;
        });
    }

    public function requestDisposal(Asset $asset, string $reason, ?string $actNumber, User $by): AssetDisposal
    {
        $this->assets->assertEditable($asset);
        if (AssetDisposal::query()->where('asset_id', $asset->id)->where('status', DisposalStatus::Pending)->exists()) {
            throw new InvalidTransitionException(__('messages.asset_disposal_pending'));
        }

        $disposal = DB::transaction(function () use ($asset, $reason, $actNumber, $by): AssetDisposal {
            $disposal = AssetDisposal::create([
                'asset_id' => $asset->id, 'status' => DisposalStatus::Pending, 'reason' => $reason,
                'act_number' => $actNumber, 'requested_by' => $by->id, 'requested_at' => CarbonImmutable::now(),
            ]);
            $this->events->record($asset, $by, AssetEventAction::DisposalRequested, new: ['act_number' => $actNumber], note: $reason);

            return $disposal;
        });

        $approvers = $this->recipients->withPermission('assets.dispose', $asset->organization_id)->reject(fn (User $u) => $u->id === $by->id);
        Notification::send($approvers, new AssetDisposalRequestedNotification($disposal, $asset));

        return $disposal;
    }

    public function decide(AssetDisposal $disposal, bool $approve, ?string $note, User $by): AssetDisposal
    {
        $asset = $disposal->asset;
        if (! $by->can('assets.dispose') || ! $by->canAccessOrganization($asset->organization_id)) {
            throw new AuthorizationException;
        }
        if ($disposal->status !== DisposalStatus::Pending) {
            throw new InvalidTransitionException(__('messages.invalid_transition'));
        }
        if ($approve && $asset->transfer_id !== null) {
            throw new InvalidTransitionException(__('messages.asset_in_transfer'));
        }

        return DB::transaction(function () use ($disposal, $asset, $approve, $note, $by): AssetDisposal {
            $disposal->forceFill([
                'status' => $approve ? DisposalStatus::Approved : DisposalStatus::Rejected,
                'decided_by' => $by->id, 'decided_at' => CarbonImmutable::now(), 'decision_note' => $note,
            ])->save();
            if ($approve) {
                $old = $asset->status->value;
                $this->assignments->closeOpen($asset, $by, __('messages.asset_written_off'));
                $asset->forceFill(['status' => AssetStatus::WrittenOff, 'holder_employee_id' => null, 'previous_status' => null])->save();
                $this->events->record($asset, $by, AssetEventAction::DisposalApproved, ['status' => $old], ['status' => AssetStatus::WrittenOff->value], $note);
            } else {
                $this->events->record($asset, $by, AssetEventAction::DisposalRejected, note: $note);
            }

            return $disposal;
        });
    }
}
