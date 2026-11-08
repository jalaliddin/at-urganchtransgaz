<?php

namespace App\Services\Exports;

use App\Models\StockMovement;
use App\Models\User;
use App\Services\Warehouse\StockQueries;

/** Movement journal export (same filters and scope as the journal screen). */
class StockMovementReport implements ReportDefinition
{
    public function __construct(private readonly StockQueries $queries) {}

    public function permission(): string
    {
        return 'warehouse.export';
    }

    public function filterNames(): array
    {
        return StockQueries::MOVEMENT_FILTERS;
    }

    public function title(string $locale): string
    {
        return __('exports.stock_movements.title', [], $locale);
    }

    public function columns(string $locale): array
    {
        return array_map(
            fn (string $key): string => __("exports.stock_movements.columns.{$key}", [], $locale),
            ['created_at', 'number', 'type', 'warehouse', 'code', 'material', 'batch', 'quantity', 'unit_cost', 'balance_after', 'user'],
        );
    }

    public function rows(User $user, array $filters, string $locale): iterable
    {
        $tz = (string) config('app.display_timezone');
        $query = StockMovement::query()->visibleTo($user)->with(['document', 'warehouse', 'material', 'creator']);
        $this->queries->filterMovements($query, $filters);

        foreach ($query->lazyById(500, 'stock_movements.id', 'id') as $movement) {
            /** @var StockMovement $movement */
            yield [
                $movement->created_at->setTimezone($tz)->format('d.m.Y H:i'),
                $movement->document->number,
                __('enums.stock_document_type.'.$movement->document->type->value, [], $locale),
                $movement->warehouse->name,
                $movement->material->code,
                $movement->material->name,
                $movement->batch === '' ? null : $movement->batch,
                (float) $movement->quantity,
                (float) $movement->unit_cost,
                (float) $movement->balance_after,
                $movement->creator?->name,
            ];
        }
    }
}
