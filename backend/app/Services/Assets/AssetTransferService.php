<?php

namespace App\Services\Assets;

use App\Enums\AssetEventAction;
use App\Enums\AssetStatus;
use App\Enums\TransferStatus;
use App\Exceptions\DomainConflictException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\AssetTransferItem;
use App\Models\RequisitionFulfillment;
use App\Models\StoredFile;
use App\Models\User;
use App\Notifications\Assets\AssetTransferIncomingNotification;
use App\Services\Files\FileStore;
use App\Services\Helpdesk\HelpdeskRecipients;
use App\Services\Support\NumberSequence;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Inter-organization hand-over: draft → sent (act generated) → received (assets
 * move) or cancelled. The sending side edits/sends/cancels, the receiving side receives.
 */
class AssetTransferService
{
    public function __construct(
        private readonly NumberSequence $numbers,
        private readonly AssetEventRecorder $events,
        private readonly AssetAssignments $assignments,
        private readonly FileStore $files,
        private readonly HelpdeskRecipients $recipients,
    ) {}

    public function create(int $fromId, int $toId, ?string $note, User $by): AssetTransfer
    {
        $this->assertSide($by, $fromId);

        return DB::transaction(function () use ($fromId, $toId, $note, $by): AssetTransfer {
            $year = (int) CarbonImmutable::now((string) config('app.display_timezone'))->format('Y');

            return AssetTransfer::create([
                'number' => sprintf('TR-%d-%05d', $year, $this->numbers->next("transfer:{$year}")),
                'from_organization_id' => $fromId,
                'to_organization_id' => $toId,
                'status' => TransferStatus::Draft,
                'note' => $note,
                'created_by' => $by->id,
            ]);
        });
    }

    /** @param  list<int>  $assetIds */
    public function addItems(AssetTransfer $transfer, array $assetIds, ?string $condition, User $by): void
    {
        $this->assertSide($by, $transfer->from_organization_id);

        DB::transaction(function () use ($transfer, $assetIds, $condition): void {
            $this->lockIn($transfer, TransferStatus::Draft);
            $assets = Asset::query()->whereIn('id', $assetIds)->lockForUpdate()->get();
            $movable = [AssetStatus::InStock, AssetStatus::InUse, AssetStatus::Reserve];
            $valid = $assets->filter(fn (Asset $a) => $a->organization_id === $transfer->from_organization_id
                && in_array($a->status, $movable, true) && ($a->transfer_id === null || $a->transfer_id === $transfer->id));
            if ($valid->count() !== count(array_unique($assetIds))) {
                throw ValidationException::withMessages(['asset_ids' => __('messages.transfer_assets_invalid')]);
            }
            foreach ($valid as $asset) {
                AssetTransferItem::query()->firstOrCreate(['transfer_id' => $transfer->id, 'asset_id' => $asset->id], ['condition_note' => $condition]);
                $asset->forceFill(['transfer_id' => $transfer->id])->save();
            }
        });
    }

    public function removeItem(AssetTransfer $transfer, int $assetId, User $by): void
    {
        $this->assertSide($by, $transfer->from_organization_id);

        DB::transaction(function () use ($transfer, $assetId): void {
            $this->lockIn($transfer, TransferStatus::Draft);
            AssetTransferItem::query()->where('transfer_id', $transfer->id)->where('asset_id', $assetId)->delete();
            Asset::query()->withoutGlobalScopes()->whereKey($assetId)->where('transfer_id', $transfer->id)->update(['transfer_id' => null]);
        });
    }

    public function send(AssetTransfer $transfer, User $by): AssetTransfer
    {
        $this->assertSide($by, $transfer->from_organization_id);

        DB::transaction(function () use ($transfer, $by): void {
            $this->lockIn($transfer, TransferStatus::Draft);
            if (! $transfer->items()->exists()) {
                throw new InvalidTransitionException(__('messages.transfer_empty'));
            }
            $transfer->forceFill(['status' => TransferStatus::Sent, 'sent_by' => $by->id, 'sent_at' => CarbonImmutable::now()])->save();
            $act = $this->renderAct($transfer, $by);
            $transfer->forceFill(['act_file_id' => $act->id])->save();
        });

        Notification::send(
            $this->recipients->withPermission('assets.transfer', $transfer->to_organization_id),
            new AssetTransferIncomingNotification($transfer),
        );

        return $transfer;
    }

    public function receive(AssetTransfer $transfer, User $by, ?string $note): AssetTransfer
    {
        $this->assertSide($by, $transfer->to_organization_id);

        return $this->receiveFor($transfer, $by, $note);
    }

    /**
     * Receives without the receiving-side permission check: the caller already holds
     * the authority (a requisition's receipt confirmation by the requesting side).
     */
    public function receiveFor(AssetTransfer $transfer, User $by, ?string $note): AssetTransfer
    {
        return DB::transaction(function () use ($transfer, $by, $note): AssetTransfer {
            $this->lockIn($transfer, TransferStatus::Sent);
            $from = $transfer->fromOrganization->name;
            $to = $transfer->toOrganization->name;
            foreach ($transfer->items()->with('asset')->get() as $item) {
                $asset = $item->asset;
                $this->assignments->closeOpen($asset, $by, __('messages.transfer_closed_assignment', ['number' => $transfer->number]));
                $old = ['organization_id' => $asset->organization_id, 'organization' => $from, 'status' => $asset->status->value];
                $asset->forceFill([
                    'organization_id' => $transfer->to_organization_id, 'department_id' => null, 'location_id' => null,
                    'room' => null, 'responsible_employee_id' => null, 'holder_employee_id' => null,
                    'status' => AssetStatus::InStock, 'transfer_id' => null,
                ])->save();
                $this->events->record($asset, $by, AssetEventAction::Transferred, $old, [
                    'organization_id' => $transfer->to_organization_id, 'organization' => $to, 'number' => $transfer->number,
                ], $note);
            }
            $transfer->forceFill(['status' => TransferStatus::Received, 'received_by' => $by->id, 'received_at' => CarbonImmutable::now()])->save();

            return $transfer;
        });
    }

    public function cancel(AssetTransfer $transfer, User $by): AssetTransfer
    {
        $this->assertSide($by, $transfer->from_organization_id);
        // An asset allocated to a requisition travels until the requesting side confirms receipt.
        if (RequisitionFulfillment::query()->where('asset_transfer_id', $transfer->id)->exists()) {
            throw new DomainConflictException(__('messages.transfer_linked_to_requisition'));
        }

        return DB::transaction(function () use ($transfer, $by): AssetTransfer {
            $this->lockIn($transfer, null);
            Asset::query()->withoutGlobalScopes()->where('transfer_id', $transfer->id)->update(['transfer_id' => null]);
            $transfer->forceFill(['status' => TransferStatus::Cancelled, 'cancelled_by' => $by->id, 'cancelled_at' => CarbonImmutable::now()])->save();

            return $transfer;
        });
    }

    /** FileAccess resolver: the act is visible to both sides. */
    public function canDownload(User $user, StoredFile $file): ?bool
    {
        $transfer = AssetTransfer::query()->where('act_file_id', $file->id)->first();
        if ($transfer === null) {
            return null;
        }

        return $user->can('assets.view') && AssetTransfer::query()->visibleTo($user)->whereKey($transfer->id)->exists();
    }

    private function renderAct(AssetTransfer $transfer, User $by): StoredFile
    {
        $transfer->load(['fromOrganization', 'toOrganization', 'items.asset.category', 'items.asset.model']);
        $pdf = Pdf::loadView('assets.transfer-act', [
            'transfer' => $transfer,
            'sender' => $by->name,
            'date' => CarbonImmutable::now((string) config('app.display_timezone'))->format('d.m.Y'),
        ])->setPaper('a4')->output();

        return $this->files->storeContents((string) $pdf, "{$transfer->number}.pdf", 'application/pdf', $transfer->from_organization_id, $by);
    }

    private function assertSide(User $user, int $organizationId): void
    {
        if (! $user->can('assets.transfer') || ! $user->canAccessOrganization($organizationId)) {
            throw new AuthorizationException;
        }
    }

    /**
     * Re-reads the transfer with a row lock and checks its status there, so a
     * concurrent send/receive/cancel cannot act on a stale copy. $status null means
     * "any open status". The caller's model is refreshed with the locked state.
     */
    private function lockIn(AssetTransfer $transfer, ?TransferStatus $status): void
    {
        $locked = AssetTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
        $allowed = $status === null ? $locked->status->isOpen() : $locked->status === $status;
        if (! $allowed) {
            throw new InvalidTransitionException(__('messages.invalid_transition'));
        }
        $transfer->setRawAttributes($locked->getAttributes(), true);
    }
}
