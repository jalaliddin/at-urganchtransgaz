<?php

namespace App\Services\Printers;

use App\Enums\CounterSource;
use App\Models\Asset;
use App\Models\CartridgeReplacement;
use App\Models\PrinterCounter;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Page counter readings (design P7). Writers of one printer serialize on its
 * asset row; the neighbours that guard the order are locking reads, so a reading
 * committed by someone else after this transaction's snapshot is still seen.
 */
class PrinterCounters
{
    public function __construct(private readonly PrinterRegistry $registry) {}

    /** @param  array<string, mixed>  $data  read_at (Y-m-d, not in the future), total, is_reset?, note? */
    public function record(Asset $printer, array $data, User $by, ?CartridgeReplacement $replacement = null): PrinterCounter
    {
        return DB::transaction(function () use ($printer, $data, $by, $replacement): PrinterCounter {
            $locked = $this->registry->lockEditable($printer);
            $date = (string) $data['read_at'];
            $total = (int) $data['total'];
            $reset = (bool) ($data['is_reset'] ?? false);

            $previous = $this->readings($locked)->where('read_at', '<=', $date)
                ->orderByDesc('read_at')->orderByDesc('id')->lockForUpdate()->first();
            $next = $this->readings($locked)->where('read_at', '>', $date)
                ->orderBy('read_at')->orderBy('id')->lockForUpdate()->first();
            if (! $reset && $previous !== null && $total < $previous->total) {
                throw ValidationException::withMessages(['total' => __('messages.counter_below_previous', [
                    'total' => $previous->total, 'date' => $previous->read_at->format('d.m.Y'),
                ])]);
            }
            if ($next !== null && ! $next->is_reset && $total > $next->total) {
                throw ValidationException::withMessages(['total' => __('messages.counter_above_next', [
                    'total' => $next->total, 'date' => $next->read_at->format('d.m.Y'),
                ])]);
            }

            return PrinterCounter::query()->create([
                'organization_id' => $locked->organization_id,
                'asset_id' => $locked->id,
                'read_at' => $date,
                'total' => $total,
                'is_reset' => $reset,
                'source' => $replacement === null ? CounterSource::Manual : CounterSource::Replacement,
                'replacement_id' => $replacement?->id,
                'note' => $data['note'] ?? null,
                'created_by' => $by->id,
            ]);
        });
    }

    /** Removes a reading unless the readings around it would then go backwards without a reset. */
    public function delete(PrinterCounter $counter): void
    {
        DB::transaction(function () use ($counter): void {
            $printer = $this->registry->lockEditable(Asset::query()->withoutGlobalScopes()->findOrFail($counter->asset_id));
            $reading = PrinterCounter::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($counter->id);
            $date = $reading->read_at->toDateString();

            $previous = $this->readings($printer)
                ->where(fn ($q) => $q->where('read_at', '<', $date)->orWhere(fn ($q) => $q->where('read_at', $date)->where('id', '<', $reading->id)))
                ->orderByDesc('read_at')->orderByDesc('id')->lockForUpdate()->first();
            $next = $this->readings($printer)
                ->where(fn ($q) => $q->where('read_at', '>', $date)->orWhere(fn ($q) => $q->where('read_at', $date)->where('id', '>', $reading->id)))
                ->orderBy('read_at')->orderBy('id')->lockForUpdate()->first();
            if ($previous !== null && $next !== null && ! $next->is_reset && $next->total < $previous->total) {
                throw ValidationException::withMessages(['total' => __('messages.counter_delete_breaks_order')]);
            }
            $reading->delete();
        });
    }

    /** @return Builder<PrinterCounter> */
    private function readings(Asset $printer): Builder
    {
        return PrinterCounter::query()->withoutGlobalScopes()->where('asset_id', $printer->id);
    }
}
