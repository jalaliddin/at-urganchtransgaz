<?php

namespace App\Services\Requisitions;

use App\Enums\AssetStatus;
use App\Enums\FulfillmentType;
use App\Enums\RequisitionAction;
use App\Enums\RequisitionItemKind;
use App\Enums\RequisitionStatus as S;
use App\Enums\StockDocumentType;
use App\Enums\TransferStatus;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\Requisition;
use App\Models\RequisitionFulfillment;
use App\Models\RequisitionItem;
use App\Models\SimCard;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Assets\AssetTransferService;
use App\Services\Sim\SimLifecycle;
use App\Services\Warehouse\StockService;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Allocation of approved resources and the end of the workflow (design R4, R5).
 * Every allocation runs under the requisition row lock, so concurrent fulfillers
 * cannot exceed the approved remainder; the resource is linked automatically: a
 * stock document (issue or transfer), an asset (with an asset transfer when it
 * comes from another organization) or a note.
 */
class RequisitionFulfilment
{
    public function __construct(
        private readonly RequisitionWorkflow $workflow,
        private readonly RequisitionNotifier $notifier,
        private readonly StockService $stock,
        private readonly AssetTransferService $transfers,
        private readonly SimLifecycle $sims,
    ) {}

    /** @param  array<string, mixed>  $data  item_id, type, quantity, warehouse_id, target_warehouse_id, material_id, batch, asset_id, sim_card_id, note */
    public function allocate(Requisition $requisition, array $data, User $by): RequisitionFulfillment
    {
        return DB::transaction(function () use ($requisition, $data, $by): RequisitionFulfillment {
            $this->workflow->lockFor('allocate', $requisition, $by);
            $item = RequisitionItem::query()->where('requisition_id', $requisition->id)->lockForUpdate()->find((int) ($data['item_id'] ?? 0));
            if ($item === null) {
                throw ValidationException::withMessages(['item_id' => __('messages.requisition_item_invalid')]);
            }
            $type = FulfillmentType::from((string) $data['type']);
            if ($type === FulfillmentType::Sim && $item->kind !== RequisitionItemKind::Sim) {
                throw ValidationException::withMessages(['type' => __('messages.allocation_sim_kind')]);
            }
            $quantity = in_array($type, [FulfillmentType::Asset, FulfillmentType::Sim], true) ? '1.000' : Decimal::of($data['quantity'] ?? '0');
            if (Decimal::cmp($quantity, '0') <= 0 || Decimal::cmp($quantity, $item->remaining()) > 0) {
                throw ValidationException::withMessages(['quantity' => __('messages.allocation_exceeds', ['remaining' => Decimal::plain($item->remaining())])]);
            }

            $link = match ($type) {
                FulfillmentType::Stock => $this->fromStock($requisition, $item, $quantity, $data, $by),
                FulfillmentType::Asset => $this->fromAssets($requisition, (int) ($data['asset_id'] ?? 0), $by),
                FulfillmentType::Note => $this->asNote($data),
                FulfillmentType::Sim => $this->fromSims($requisition, (int) ($data['sim_card_id'] ?? 0), $by),
            };

            $item->forceFill(['fulfilled_quantity' => Decimal::add($item->fulfilled_quantity, $quantity)])->save();
            $fulfillment = RequisitionFulfillment::create([
                'requisition_id' => $requisition->id, 'item_id' => $item->id, 'type' => $type, 'quantity' => $quantity,
                'fulfilled_by' => $by->id, 'note' => trim((string) ($data['note'] ?? '')) ?: null, ...$link,
            ]);

            $before = $requisition->status;
            $after = $this->allFulfilled($requisition) ? S::Fulfilled : S::PartiallyFulfilled;
            $payload = ['item' => $item->name, 'type' => $type->value, 'quantity' => $quantity];
            $after === $before
                ? $this->workflow->record($requisition, RequisitionAction::Allocated, $by, payload: $payload)
                : $this->workflow->move($requisition, $after, RequisitionAction::Allocated, $by, payload: $payload);

            if ($after === S::Fulfilled) {
                $this->notifier->send($requisition, 'fulfilled', $this->notifier->requester($requisition), $by);
            } elseif ($before !== S::PartiallyFulfilled) {
                $this->notifier->send($requisition, 'partially_fulfilled', $this->notifier->requester($requisition), $by);
            }

            return $fulfillment;
        });
    }

    /** Ends a partial allocation: what was not allocated will not be. */
    public function complete(Requisition $requisition, ?string $comment, User $by): Requisition
    {
        DB::transaction(function () use ($requisition, $comment, $by): void {
            $this->workflow->lockFor('complete', $requisition, $by);
            if (trim((string) $comment) === '') {
                throw ValidationException::withMessages(['comment' => __('messages.requisition_comment_required')]);
            }
            $this->workflow->move($requisition, S::Fulfilled, RequisitionAction::Completed, $by, $comment);
            $this->notifier->send($requisition, 'fulfilled', $this->notifier->requester($requisition), $by, $comment);
        });

        return $requisition;
    }

    /** The requesting side confirms receipt; assets travelling by transfer arrive now. */
    public function receive(Requisition $requisition, ?string $comment, User $by): Requisition
    {
        DB::transaction(function () use ($requisition, $comment, $by): void {
            $this->workflow->lockFor('receive', $requisition, $by);
            $transferIds = RequisitionFulfillment::query()->where('requisition_id', $requisition->id)->whereNotNull('asset_transfer_id')->pluck('asset_transfer_id');
            foreach (AssetTransfer::query()->whereIn('id', $transferIds)->where('status', TransferStatus::Sent)->get() as $transfer) {
                $this->transfers->receiveFor($transfer, $by, __('messages.requisition_received_note', ['number' => $requisition->number]));
            }
            $this->workflow->move($requisition, S::Received, RequisitionAction::Received, $by, $comment,
                ['received_by' => $by->id, 'received_at' => CarbonImmutable::now()]);
            $central = User::query()->withoutGlobalScopes()->whereIn('id', array_filter([$requisition->approved_by, $requisition->reviewer_id]))->get();
            $this->notifier->send($requisition, 'received', $central, $by, $comment);
        });

        return $requisition;
    }

    public function close(Requisition $requisition, User $by): Requisition
    {
        DB::transaction(function () use ($requisition, $by): void {
            $this->workflow->lockFor('close', $requisition, $by);
            $this->workflow->move($requisition, S::Closed, RequisitionAction::Closed, $by, attributes: [
                'closed_by' => $by->id, 'closed_at' => CarbonImmutable::now(),
            ]);
        });

        return $requisition;
    }

    /**
     * Issue to the requesting organization, or transfer into one of its warehouses.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fromStock(Requisition $requisition, RequisitionItem $item, string $quantity, array $data, User $by): array
    {
        $materialId = $item->material_id ?? (isset($data['material_id']) ? (int) $data['material_id'] : null);
        if ($materialId === null) {
            throw ValidationException::withMessages(['material_id' => __('messages.allocation_material_required')]);
        }
        $targetId = isset($data['target_warehouse_id']) ? (int) $data['target_warehouse_id'] : null;
        if ($targetId !== null && ! Warehouse::query()->withoutGlobalScopes()->whereKey($targetId)->where('organization_id', $requisition->organization_id)->exists()) {
            throw ValidationException::withMessages(['target_warehouse_id' => __('messages.allocation_target_invalid')]);
        }
        $type = $targetId === null ? StockDocumentType::Issue : StockDocumentType::Transfer;
        if (! $by->can($type->permission())) {
            throw new AuthorizationException;
        }
        $document = $this->stock->post($type, array_filter([
            'warehouse_id' => (int) ($data['warehouse_id'] ?? 0),
            'target_warehouse_id' => $targetId,
            'recipient_organization_id' => $targetId === null ? $requisition->organization_id : null,
            'recipient_employee_id' => $targetId === null ? $requisition->recipient_employee_id : null,
            'reason' => __('messages.requisition_issue_reason', ['number' => $requisition->number]),
            'requisition_id' => $requisition->id,
        ], fn ($v) => $v !== null), [[
            'material_id' => $materialId, 'quantity' => $quantity, 'batch' => $data['batch'] ?? null,
        ]], $by);

        return ['stock_document_id' => $document->id];
    }

    /**
     * An available asset (in stock or reserve, not travelling, not promised to another
     * open requisition). From another organization it is sent by an asset transfer.
     *
     * @return array<string, mixed>
     */
    private function fromAssets(Requisition $requisition, int $assetId, User $by): array
    {
        $asset = Asset::query()->visibleTo($by)->lockForUpdate()->find($assetId);
        $promised = $asset !== null && RequisitionQueries::promised('asset_id', $asset->id);
        if ($asset === null || ! in_array($asset->status, [AssetStatus::InStock, AssetStatus::Reserve], true) || $asset->transfer_id !== null || $promised) {
            throw ValidationException::withMessages(['asset_id' => __('messages.allocation_asset_unavailable')]);
        }
        if ($asset->organization_id === $requisition->organization_id) {
            return ['asset_id' => $asset->id];
        }
        $note = __('messages.requisition_issue_reason', ['number' => $requisition->number]);
        $transfer = $this->transfers->create($asset->organization_id, $requisition->organization_id, $note, $by);
        $this->transfers->addItems($transfer, [$asset->id], null, $by);
        $this->transfers->send($transfer, $by);

        return ['asset_id' => $asset->id, 'asset_transfer_id' => $transfer->id];
    }

    /**
     * A free SIM card of the fulfiller's scope that no other open requisition was given; it moves to the
     * requisition's organization at once (no receipt step: the card itself is the delivery).
     *
     * @return array<string, mixed>
     */
    private function fromSims(Requisition $requisition, int $cardId, User $by): array
    {
        $card = SimCard::query()->withoutGlobalScopes()->lockForUpdate()->find($cardId);
        $promised = $card !== null && RequisitionQueries::promised('sim_card_id', $card->id);
        if ($card === null || ! $by->canAccessOrganization($card->organization_id) || ! $card->status->isAvailable() || $promised) {
            throw ValidationException::withMessages(['sim_card_id' => __('messages.allocation_sim_unavailable')]);
        }
        $this->sims->allocate($card, $requisition, $by);

        return ['sim_card_id' => $card->id];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function asNote(array $data): array
    {
        $note = trim((string) ($data['note'] ?? ''));
        if ($note === '') {
            throw ValidationException::withMessages(['note' => __('messages.allocation_note_required')]);
        }

        return ['note' => $note];
    }

    private function allFulfilled(Requisition $requisition): bool
    {
        return RequisitionItem::query()->where('requisition_id', $requisition->id)->get()
            ->every(fn (RequisitionItem $i) => Decimal::cmp($i->remaining(), '0') === 0);
    }
}
