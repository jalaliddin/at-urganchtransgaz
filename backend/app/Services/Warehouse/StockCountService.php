<?php

namespace App\Services\Warehouse;

use App\Enums\StockCountStatus;
use App\Enums\StockDocumentType;
use App\Exceptions\DomainConflictException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Material;
use App\Models\StockBalance;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Support\NumberSequence;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock count of one warehouse (design W6, W8): snapshot of lots at start, counted
 * quantities, close into one adjustment document. While open, StockService refuses
 * documents for the warehouse, so the snapshot stays the book quantity.
 */
class StockCountService
{
    public function __construct(private readonly NumberSequence $numbers, private readonly StockService $stock) {}

    public function start(Warehouse $warehouse, ?string $note, User $by): StockCount
    {
        try {
            return DB::transaction(function () use ($warehouse, $note, $by): StockCount {
                $locked = Warehouse::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($warehouse->id);
                if (! $locked->is_active) {
                    throw ValidationException::withMessages(['warehouse_id' => __('messages.warehouse_inactive')]);
                }
                if (StockCount::query()->withoutGlobalScopes()->where('open_key', $locked->id)->exists()) {
                    throw new DomainConflictException(__('messages.stock_count_open_exists'));
                }
                $year = CarbonImmutable::now((string) config('app.display_timezone'))->year;
                $count = StockCount::query()->withoutGlobalScopes()->create([
                    'organization_id' => $locked->organization_id, 'warehouse_id' => $locked->id,
                    'number' => sprintf('SC-%d-%05d', $year, $this->numbers->next("stock-count:{$year}")),
                    'status' => StockCountStatus::Open, 'open_key' => $locked->id, 'note' => $note,
                    'started_by' => $by->id, 'started_at' => CarbonImmutable::now(),
                ]);
                StockBalance::query()->where('warehouse_id', $locked->id)->where('quantity', '>', 0)->orderBy('material_id')->orderBy('batch')
                    ->get()->each(fn (StockBalance $lot) => StockCountLine::create([
                        'count_id' => $count->id, 'material_id' => $lot->material_id, 'batch' => $lot->batch,
                        'expires_at' => $lot->expires_at, 'expected_quantity' => $lot->quantity, 'unit_cost' => $lot->unit_cost,
                    ]));

                return $count;
            });
        } catch (QueryException $e) {
            // open_key is unique: a parallel start lost the race.
            if ((string) $e->getCode() === '23000') {
                throw new DomainConflictException(__('messages.stock_count_open_exists'));
            }
            throw $e;
        }
    }

    /** @param  list<array{id: int, counted_quantity: string|int|float|null}>  $entries */
    public function record(StockCount $count, array $entries, User $by): void
    {
        DB::transaction(function () use ($count, $entries, $by): void {
            $this->lockOpen($count);
            $lines = StockCountLine::query()->where('count_id', $count->id)->whereIn('id', array_column($entries, 'id'))->get()->keyBy('id');
            foreach ($entries as $i => $entry) {
                $line = $lines->get($entry['id']);
                if ($line === null) {
                    throw ValidationException::withMessages(["lines.{$i}.id" => __('messages.stock_count_line_invalid')]);
                }
                $counted = $entry['counted_quantity'] === null ? null : Decimal::of($entry['counted_quantity']);
                $line->forceFill([
                    'counted_quantity' => $counted, 'counted_by' => $counted === null ? null : $by->id,
                    'counted_at' => $counted === null ? null : CarbonImmutable::now(),
                ])->save();
            }
        });
    }

    /** A lot found on the shelf that the books do not have (expected 0). */
    public function addLine(StockCount $count, Material $material, ?string $batch, ?string $expiresAt, string $counted, User $by): StockCountLine
    {
        $batch = $material->track_batches ? trim((string) $batch) : '';
        if ($material->track_batches && $batch === '') {
            throw ValidationException::withMessages(['batch' => __('messages.batch_required')]);
        }

        return DB::transaction(function () use ($count, $material, $batch, $expiresAt, $counted, $by): StockCountLine {
            $this->lockOpen($count);
            if (StockCountLine::query()->where('count_id', $count->id)->where('material_id', $material->id)->where('batch', $batch)->exists()) {
                throw ValidationException::withMessages(['material_id' => __('messages.stock_count_line_exists')]);
            }
            $lot = StockBalance::query()->where('warehouse_id', $count->warehouse_id)->where('material_id', $material->id)->where('batch', $batch)->first();
            $expiry = $lot?->expires_at?->toDateString() ?? ($material->track_batches ? $expiresAt : null);
            if ($material->track_expiry && $expiry === null) {
                throw ValidationException::withMessages(['expires_at' => __('messages.expiry_required')]);
            }

            return StockCountLine::create([
                'count_id' => $count->id, 'material_id' => $material->id, 'batch' => $batch, 'expires_at' => $expiry,
                'expected_quantity' => '0', 'counted_quantity' => $counted, 'unit_cost' => $lot->unit_cost ?? '0',
                'counted_by' => $by->id, 'counted_at' => CarbonImmutable::now(),
            ]);
        });
    }

    public function close(StockCount $count, User $by): StockCount
    {
        return DB::transaction(function () use ($count, $by): StockCount {
            Warehouse::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($count->warehouse_id);
            $this->lockOpen($count);
            $lines = StockCountLine::query()->with('material')->where('count_id', $count->id)->orderBy('id')->get();
            $uncounted = $lines->whereNull('counted_quantity')->count();
            if ($uncounted > 0) {
                throw ValidationException::withMessages(['lines' => __('messages.stock_count_uncounted', ['count' => $uncounted])]);
            }

            $adjustment = [];
            foreach ($lines as $line) {
                $diff = Decimal::sub((string) $line->counted_quantity, $line->expected_quantity);
                if (Decimal::cmp($diff, '0') !== 0) {
                    $adjustment[] = [
                        'material_id' => $line->material_id, 'quantity' => $diff, 'batch' => $line->batch,
                        'expires_at' => $line->expires_at?->toDateString(),
                        'unit_cost' => Decimal::cmp($diff, '0') > 0 ? $line->unit_cost : null,
                    ];
                }
            }
            $document = $adjustment === [] ? null : $this->stock->post(StockDocumentType::Adjustment, [
                'warehouse_id' => $count->warehouse_id, 'stock_count_id' => $count->id,
                'reason' => __('messages.stock_count_reason', ['number' => $count->number]),
            ], $adjustment, $by);

            $count->forceFill([
                'status' => StockCountStatus::Closed, 'open_key' => null, 'closed_by' => $by->id,
                'closed_at' => CarbonImmutable::now(), 'adjustment_document_id' => $document?->id,
            ])->save();

            return $count;
        });
    }

    public function cancel(StockCount $count, User $by): StockCount
    {
        return DB::transaction(function () use ($count, $by): StockCount {
            $this->lockOpen($count);
            $count->forceFill(['status' => StockCountStatus::Cancelled, 'open_key' => null, 'closed_by' => $by->id, 'closed_at' => CarbonImmutable::now()])->save();

            return $count;
        });
    }

    private function lockOpen(StockCount $count): void
    {
        $locked = StockCount::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($count->id);
        if ($locked->status !== StockCountStatus::Open) {
            throw new InvalidTransitionException(__('messages.stock_count_closed'));
        }
        $count->setRawAttributes($locked->getAttributes(), true);
    }
}
