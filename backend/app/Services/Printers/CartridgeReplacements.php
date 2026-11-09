<?php

namespace App\Services\Printers;

use App\Enums\AuditEvent;
use App\Enums\ReplacementSource;
use App\Enums\StockDocumentType;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Asset;
use App\Models\CartridgeModel;
use App\Models\CartridgeReplacement;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Warehouse\StockService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cartridge replacements (design P4–P6, P10). A warehouse replacement and its
 * stock issue are one transaction; cancelling posts a return of that issue.
 * Lock order: printer asset row → replacement row → warehouse (StockService).
 */
class CartridgeReplacements
{
    public function __construct(
        private readonly PrinterRegistry $registry,
        private readonly PrinterCounters $counters,
        private readonly StockService $stock,
        private readonly PrinterPages $pages,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated: cartridge_model_id, replaced_at, source, warehouse_id?, note?, counter?{total, is_reset?}
     */
    public function replace(Asset $printer, array $data, User $by): CartridgeReplacement
    {
        return DB::transaction(function () use ($printer, $data, $by): CartridgeReplacement {
            $locked = $this->registry->lockEditable($printer);
            $cartridge = CartridgeModel::query()->whereKey((int) $data['cartridge_model_id'])->where('is_active', true)->firstOrFail();
            $this->assertCompatible($locked, $cartridge);
            $source = ReplacementSource::from((string) $data['source']);
            $date = (string) $data['replaced_at'];

            $document = null;
            if ($source === ReplacementSource::Warehouse) {
                $document = $this->rekeyed('warehouse_id', fn () => $this->stock->post(StockDocumentType::Issue, [
                    'warehouse_id' => (int) $data['warehouse_id'],
                    'document_date' => $date,
                    'recipient_organization_id' => $locked->organization_id,
                    'recipient_employee_id' => $locked->holder_employee_id,
                    'asset_id' => $locked->id,
                    'reason' => __('messages.cartridge_replacement_reason', ['printer' => $locked->inventory_number, 'cartridge' => $cartridge->name]),
                ], [['material_id' => $cartridge->material_id, 'quantity' => 1]], $by));
            }

            $replacement = CartridgeReplacement::query()->create([
                'organization_id' => $locked->organization_id,
                'asset_id' => $locked->id,
                'cartridge_model_id' => $cartridge->id,
                'replaced_at' => $date,
                'source' => $source,
                'warehouse_id' => $document?->warehouse_id,
                'stock_document_id' => $document?->id,
                'note' => $data['note'] ?? null,
                'performed_by' => $by->id,
            ]);

            if (isset($data['counter']['total'])) {
                $this->rekeyed('counter.total', fn () => $this->counters->record($locked, [
                    'read_at' => $date, 'total' => $data['counter']['total'], 'is_reset' => $data['counter']['is_reset'] ?? false,
                ], $by, $replacement));
            }

            $this->audit->record(AuditEvent::Created, $replacement, [], [
                'asset_id' => $locked->id, 'cartridge_model_id' => $cartridge->id, 'replaced_at' => $date,
                'source' => $source->value, 'stock_document_id' => $document?->id,
            ]);

            return $replacement;
        }, 3);
    }

    public function cancel(CartridgeReplacement $replacement, string $reason, User $by): CartridgeReplacement
    {
        return DB::transaction(function () use ($replacement, $reason, $by): CartridgeReplacement {
            $this->registry->lock(Asset::query()->withoutGlobalScopes()->findOrFail($replacement->asset_id));
            $locked = CartridgeReplacement::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($replacement->id);
            if ($locked->cancelled_at !== null) {
                throw new InvalidTransitionException(__('messages.replacement_already_cancelled'));
            }

            $return = null;
            if ($locked->stock_document_id !== null && $locked->warehouse_id !== null) {
                $material = (int) CartridgeModel::query()->whereKey($locked->cartridge_model_id)->value('material_id');
                $return = $this->rekeyed('reason', fn () => $this->stock->post(StockDocumentType::Return, [
                    'warehouse_id' => $locked->warehouse_id,
                    'related_document_id' => $locked->stock_document_id,
                    'reason' => $reason,
                ], [['material_id' => $material, 'quantity' => 1]], $by));
            }

            $locked->forceFill([
                'cancelled_at' => CarbonImmutable::now(),
                'cancelled_by' => $by->id,
                'cancel_reason' => $reason,
                'return_document_id' => $return?->id,
            ])->save();
            $this->audit->record(AuditEvent::Updated, $locked, ['cancelled_at' => null], [
                'cancelled_at' => $locked->cancelled_at?->toIso8601String(), 'cancel_reason' => $reason, 'return_document_id' => $return?->id,
            ]);

            return $locked;
        }, 3);
    }

    /**
     * Pages printed since the previous active replacement of the same cartridge in the same printer, when both carry a
     * counter reading (design P10).
     *
     * @param  Collection<int, CartridgeReplacement>  $replacements
     * @return array<int, int|null> replacement id => pages
     */
    public function pagesSincePrevious(Collection $replacements): array
    {
        $assetIds = $replacements->pluck('asset_id')->unique()->map(fn ($id) => (int) $id)->values()->all();
        if ($assetIds === []) {
            return [];
        }

        $cumulative = [];
        $running = [];
        foreach ($this->pages->readings($assetIds)->orderBy('asset_id')->orderBy('read_at')->orderBy('id')->get(['id', 'asset_id', 'pages']) as $reading) {
            $asset = (int) $reading->asset_id;
            $running[$asset] = ($running[$asset] ?? 0) + (int) ($reading->pages ?? 0);
            $cumulative[(int) $reading->id] = $running[$asset];
        }

        $withCounters = CartridgeReplacement::query()->withoutGlobalScopes()->whereIn('cartridge_replacements.asset_id', $assetIds)
            ->join('printer_counters', 'printer_counters.replacement_id', '=', 'cartridge_replacements.id')
            ->orderBy('cartridge_replacements.replaced_at')->orderBy('cartridge_replacements.id')
            ->get(['cartridge_replacements.id', 'cartridge_replacements.asset_id', 'cartridge_replacements.cartridge_model_id',
                'cartridge_replacements.cancelled_at', 'printer_counters.id AS counter_id']);

        $result = [];
        $previous = [];
        foreach ($withCounters as $row) {
            $key = $row->asset_id.':'.$row->cartridge_model_id;
            $counter = (int) $row->getAttribute('counter_id');
            $before = $previous[$key] ?? null;
            $result[$row->id] = $before !== null && isset($cumulative[$counter], $cumulative[$before]) && $cumulative[$counter] >= $cumulative[$before]
                ? $cumulative[$counter] - $cumulative[$before] : null;
            if ($row->cancelled_at === null) {
                $previous[$key] = $counter;
            }
        }

        return $result;
    }

    /** Design P5: a model with a compatibility list accepts only its cartridges. */
    private function assertCompatible(Asset $printer, CartridgeModel $cartridge): void
    {
        if ($printer->model_id === null) {
            return;
        }
        $fits = DB::table('printer_cartridge_compatibilities')->where('asset_model_id', $printer->model_id);
        if ((clone $fits)->exists() && ! $fits->where('cartridge_model_id', $cartridge->id)->exists()) {
            throw ValidationException::withMessages(['cartridge_model_id' => __('messages.cartridge_not_compatible')]);
        }
    }

    /**
     * Runs $work and reports its validation and stock errors under one form field.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    private function rekeyed(string $field, callable $work): mixed
    {
        try {
            return $work();
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([$field => collect($e->errors())->flatten()->first() ?? $e->getMessage()]);
        } catch (InsufficientStockException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }
    }
}
