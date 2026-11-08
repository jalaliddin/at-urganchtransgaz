<?php

namespace App\Services\Warehouse;

use App\Enums\AuditEvent;
use App\Enums\StockCountStatus;
use App\Enums\StockDocumentType;
use App\Exceptions\WarehouseCountingException;
use App\Models\Material;
use App\Models\StockCount;
use App\Models\StockDocument;
use App\Models\StockDocumentLine;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Audit\AuditLogger;
use App\Services\Support\NumberSequence;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only writer of stock. Every operation is one posted document: inside one
 * transaction the involved warehouses are locked (ascending id), the open-count
 * freeze is checked, lots change through StockLedger and every lot change is
 * journaled. Documents are never edited afterwards (design W3, W5).
 *
 * Authorization is the caller's job; this class enforces the stock rules.
 */
class StockService
{
    private const MAX_LINES = 200;

    /** @var list<string> */
    private const HEADER = [
        'supplier', 'supplier_document', 'recipient_organization_id', 'recipient_employee_id', 'asset_id', 'reason',
        'related_document_id', 'stock_count_id', 'requisition_id', 'file_id',
    ];

    public function __construct(
        private readonly StockLedger $ledger,
        private readonly NumberSequence $numbers,
        private readonly AuditLogger $audit,
        private readonly StockAlerts $alerts,
    ) {}

    /**
     * @param  array<string, mixed>  $header  warehouse_id, target_warehouse_id (transfer), document_date and HEADER keys
     * @param  array<int, array<string, mixed>>  $lines  material_id, quantity, batch?, expires_at?, unit_cost?, note?
     */
    public function post(StockDocumentType $type, array $header, array $lines, User $by): StockDocument
    {
        $prepared = $this->prepare($type, $lines);

        $document = DB::transaction(function () use ($type, $header, $prepared, $by): StockDocument {
            [$source, $target] = $this->lockWarehouses($type, $header);
            $this->assertNotCounting([$source->id, $target?->id], isset($header['stock_count_id']) ? (int) $header['stock_count_id'] : null);
            $issueCosts = $type === StockDocumentType::Return ? $this->checkReturn($header, $prepared) : [];

            $year = CarbonImmutable::now((string) config('app.display_timezone'))->year;
            $document = StockDocument::create([
                ...array_intersect_key($header, array_flip(self::HEADER)),
                'organization_id' => $source->organization_id,
                'number' => sprintf('%s-%d-%05d', $type->prefix(), $year, $this->numbers->next("stock:{$type->prefix()}:{$year}")),
                'type' => $type,
                'warehouse_id' => $source->id,
                'target_warehouse_id' => $target?->id,
                'target_organization_id' => $target?->organization_id,
                'document_date' => $header['document_date'] ?? CarbonImmutable::now((string) config('app.display_timezone'))->toDateString(),
                'recipient_organization_id' => $header['recipient_organization_id']
                    ?? ($type === StockDocumentType::Issue ? $source->organization_id : null),
                'created_by' => $by->id,
            ]);

            $total = '0.00';
            foreach ($prepared as $index => $row) {
                $total = Decimal::add($total, $this->apply($type, $document, $row, $index, $source, $target, $issueCosts, $by), 2);
            }
            $document->forceFill(['total_amount' => $total])->save();

            $pairs = [];
            foreach ($prepared as $row) {
                foreach (array_filter([$source->id, $target?->id]) as $warehouseId) {
                    $pairs["{$warehouseId}:{$row['material']->id}"] = [$warehouseId, $row['material']->id];
                }
            }
            DB::afterCommit(function () use ($pairs): void {
                foreach ($pairs as [$warehouseId, $materialId]) {
                    $this->alerts->syncLowStock($warehouseId, $materialId);
                }
            });

            $this->audit->record(AuditEvent::Created, $document, [], [
                'number' => $document->number, 'type' => $type->value, 'warehouse_id' => $source->id,
                'target_warehouse_id' => $target?->id, 'lines' => count($prepared), 'total_amount' => $total,
            ]);

            return $document;
        }, 3);

        return $document;
    }

    /**
     * Validates lines against the catalog and normalizes numbers and batches.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return list<array{material: Material, quantity: string, batch: string|null, expires_at: string|null, unit_cost: string|null, note: string|null}>
     */
    private function prepare(StockDocumentType $type, array $lines): array
    {
        if ($lines === [] || count($lines) > self::MAX_LINES) {
            throw ValidationException::withMessages(['lines' => __('messages.stock_lines_required', ['max' => self::MAX_LINES])]);
        }
        /** @var Collection<int, Material> $materials */
        $materials = Material::query()->whereIn('id', array_map(fn ($l) => (int) ($l['material_id'] ?? 0), $lines))->get()->keyBy('id');

        $errors = [];
        $prepared = [];
        foreach (array_values($lines) as $i => $line) {
            $material = $materials->get((int) ($line['material_id'] ?? 0));
            if ($material === null || ($type === StockDocumentType::Receipt && ! $material->is_active)) {
                $errors["lines.{$i}.material_id"] = __('messages.material_unavailable');

                continue;
            }
            $quantity = Decimal::isNumeric($line['quantity'] ?? null) ? Decimal::of($line['quantity']) : null;
            $sign = $quantity === null ? 0 : Decimal::cmp($quantity, '0');
            if ($sign === 0 || ($sign < 0 && $type !== StockDocumentType::Adjustment)) {
                $errors["lines.{$i}.quantity"] = __('messages.stock_quantity_invalid');

                continue;
            }
            $increases = in_array($type, [StockDocumentType::Receipt, StockDocumentType::Return], true)
                || ($type === StockDocumentType::Adjustment && $sign > 0);

            $batch = null;
            if (! $material->track_batches) {
                $batch = '';
            } elseif (trim((string) ($line['batch'] ?? '')) !== '') {
                $batch = mb_substr(trim((string) $line['batch']), 0, 100);
            } elseif ($increases) {
                $errors["lines.{$i}.batch"] = __('messages.batch_required');

                continue;
            }

            // Expiry lives on batches: required when tracked, optional for other batched materials.
            $expiresAt = $material->track_batches && ! empty($line['expires_at'])
                ? CarbonImmutable::parse((string) $line['expires_at'])->toDateString() : null;
            if ($material->track_expiry && $type === StockDocumentType::Receipt && $expiresAt === null) {
                $errors["lines.{$i}.expires_at"] = __('messages.expiry_required');

                continue;
            }

            $cost = $line['unit_cost'] ?? null;
            if ($cost !== null && (! Decimal::isNumeric($cost) || Decimal::cmp(Decimal::of($cost, 2), '0', 2) < 0)) {
                $errors["lines.{$i}.unit_cost"] = __('messages.stock_cost_invalid');

                continue;
            }

            $prepared[] = [
                'material' => $material, 'quantity' => $quantity, 'batch' => $batch, 'expires_at' => $expiresAt,
                'unit_cost' => $cost === null ? null : Decimal::of($cost, 2),
                'note' => isset($line['note']) ? mb_substr((string) $line['note'], 0, 255) : null,
            ];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $prepared;
    }

    /**
     * @param  array<string, mixed>  $header
     * @return array{0: Warehouse, 1: Warehouse|null}
     */
    private function lockWarehouses(StockDocumentType $type, array $header): array
    {
        $sourceId = (int) ($header['warehouse_id'] ?? 0);
        $targetId = $type === StockDocumentType::Transfer ? (int) ($header['target_warehouse_id'] ?? 0) : null;
        if ($targetId !== null && ($targetId === 0 || $targetId === $sourceId)) {
            throw ValidationException::withMessages(['target_warehouse_id' => __('messages.transfer_target_invalid')]);
        }

        $locked = Warehouse::query()->withoutGlobalScopes()->whereIn('id', array_filter([$sourceId, $targetId]))
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        $source = $locked->get($sourceId);
        if ($source === null || ! $source->is_active) {
            throw ValidationException::withMessages(['warehouse_id' => __('messages.warehouse_inactive')]);
        }
        $target = $targetId === null ? null : $locked->get($targetId);
        if ($targetId !== null && ($target === null || ! $target->is_active)) {
            throw ValidationException::withMessages(['target_warehouse_id' => __('messages.transfer_target_invalid')]);
        }

        return [$source, $target];
    }

    /** @param  array<int, int|null>  $warehouseIds */
    private function assertNotCounting(array $warehouseIds, ?int $ownCountId): void
    {
        $counting = StockCount::query()->withoutGlobalScopes()->whereIn('warehouse_id', array_filter($warehouseIds))
            ->where('status', StockCountStatus::Open)
            ->when($ownCountId !== null, fn ($q) => $q->where('id', '!=', $ownCountId))
            ->exists();
        if ($counting) {
            throw new WarehouseCountingException;
        }
    }

    /**
     * A return linked to an issue may bring back at most what that issue gave out
     * (minus earlier returns), at the issue's cost. The issue row is locked so two
     * returns of it cannot both pass.
     *
     * @param  array<string, mixed>  $header
     * @param  list<array{material: Material, quantity: string, batch: string|null, expires_at: string|null, unit_cost: string|null, note: string|null}>  $prepared
     * @return array<int, string> unit cost per material id
     */
    private function checkReturn(array $header, array $prepared): array
    {
        if (empty($header['related_document_id'])) {
            return [];
        }
        $issue = StockDocument::query()->lockForUpdate()->find((int) $header['related_document_id']);
        if ($issue === null || $issue->type !== StockDocumentType::Issue) {
            throw ValidationException::withMessages(['related_document_id' => __('messages.return_needs_issue')]);
        }

        $issued = StockMovement::query()->withoutGlobalScopes()->where('document_id', $issue->id)
            ->groupBy('material_id')->selectRaw('material_id, -SUM(quantity) AS qty, -SUM(quantity * unit_cost) AS value')
            ->get()->keyBy('material_id');
        $returned = StockMovement::query()->withoutGlobalScopes()
            ->whereIn('document_id', StockDocument::query()->select('id')->where('type', StockDocumentType::Return)->where('related_document_id', $issue->id))
            ->groupBy('material_id')->selectRaw('material_id, SUM(quantity) AS qty')->pluck('qty', 'material_id');

        $wanted = [];
        $costs = [];
        $errors = [];
        foreach ($prepared as $i => $row) {
            $id = $row['material']->id;
            $line = $issued->get($id);
            if ($line === null) {
                $errors["lines.{$i}.material_id"] = __('messages.return_material_not_issued');

                continue;
            }
            $wanted[$id] = Decimal::add($wanted[$id] ?? '0', $row['quantity']);
            $left = Decimal::sub(Decimal::of((string) $line->getAttribute('qty')), Decimal::of((string) ($returned[$id] ?? '0')));
            if (Decimal::cmp($wanted[$id], $left) > 0) {
                $errors["lines.{$i}.quantity"] = __('messages.return_exceeds_issue', ['available' => $left]);
            }
            $costs[$id] = Decimal::div(Decimal::of((string) $line->getAttribute('value'), 6), Decimal::of((string) $line->getAttribute('qty')), 2);
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $costs;
    }

    /**
     * Applies one line and returns its amount.
     *
     * @param  array{material: Material, quantity: string, batch: string|null, expires_at: string|null, unit_cost: string|null, note: string|null}  $row
     * @param  array<int, string>  $issueCosts
     */
    private function apply(
        StockDocumentType $type, StockDocument $document, array $row, int $index, Warehouse $source, ?Warehouse $target,
        array $issueCosts, User $by,
    ): string {
        $material = $row['material'];
        $unitCost = $row['unit_cost'] ?? ($issueCosts[$material->id] ?? null);
        $line = StockDocumentLine::create([
            'document_id' => $document->id, 'material_id' => $material->id, 'batch' => $row['batch'] === '' ? null : $row['batch'],
            'expires_at' => $row['expires_at'], 'quantity' => $row['quantity'], 'unit_cost' => $unitCost, 'note' => $row['note'],
        ]);
        $key = "lines.{$index}";

        $amount = '0.00';
        if (Decimal::cmp($row['quantity'], '0') > 0 && in_array($type, [StockDocumentType::Receipt, StockDocumentType::Return, StockDocumentType::Adjustment], true)) {
            $movement = $this->ledger->increase($document, $line, $source->id, $source->organization_id, $material,
                (string) $row['batch'], $row['expires_at'], $row['quantity'], $unitCost, $by, $key);
            $amount = Decimal::mul($row['quantity'], $movement->unit_cost);
        } else {
            $quantity = Decimal::abs($row['quantity']);
            foreach ($this->ledger->allocate($source->id, $material, $row['batch'], $quantity) as [$lot, $take]) {
                $this->ledger->decrease($document, $line, $lot, $source->organization_id, $take, $by);
                if ($target !== null) {
                    $this->ledger->increase($document, $line, $target->id, $target->organization_id, $material,
                        $lot->batch, $lot->expires_at?->toDateString(), $take, $lot->unit_cost, $by, $key);
                }
                $amount = Decimal::add($amount, Decimal::mul($take, $lot->unit_cost), 2);
            }
            if ($type === StockDocumentType::Adjustment) {
                $amount = Decimal::neg($amount);
            }
        }
        $value = Decimal::abs($amount);
        $line->forceFill([
            'unit_cost' => $line->unit_cost ?? ($value !== '0.00' ? Decimal::div($value, Decimal::abs($row['quantity'])) : null),
            'amount' => $amount,
        ])->save();

        return $amount;
    }
}
