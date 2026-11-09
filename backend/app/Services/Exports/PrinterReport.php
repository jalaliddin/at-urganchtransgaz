<?php

namespace App\Services\Exports;

use App\Models\Asset;
use App\Models\User;
use App\Services\Printers\PrinterRegistry;

/** Printer registry of the user's organizations. */
class PrinterReport implements ReportDefinition
{
    public function __construct(private readonly PrinterRegistry $registry) {}

    public function permission(): string
    {
        return 'printers.export';
    }

    public function filterNames(): array
    {
        return PrinterRegistry::FILTERS;
    }

    public function title(string $locale): string
    {
        return __('exports.printers.title', [], $locale);
    }

    public function columns(string $locale): array
    {
        return array_map(fn (string $k) => __("exports.printers.columns.{$k}", [], $locale), [
            'inventory_number', 'name', 'model', 'serial_number', 'organization', 'location', 'holder', 'ip_address', 'connection',
            'status', 'last_counter', 'last_counter_at', 'last_replacement',
        ]);
    }

    public function rows(User $user, array $filters, string $locale): iterable
    {
        $query = $this->registry->filtered($user, $filters)->with(['organization', 'model', 'location', 'holder', 'printer']);
        foreach ($query->lazy(500)->chunk(500) as $chunk) {
            $ids = $chunk->map(fn (Asset $a) => $a->id)->values()->all();
            [$counters, $replacements] = [$this->registry->lastCounters($ids), $this->registry->lastReplacements($ids)];
            foreach ($chunk as $a) {
                $counter = $counters[$a->id] ?? null;
                $replacement = $replacements[$a->id] ?? null;
                yield [
                    $a->inventory_number, $a->name, $a->model?->name, $a->serial_number, $a->organization->name,
                    trim(($a->location->name ?? '').' '.($a->room ?? '')) ?: null, $a->holder?->full_name, $a->ip_address,
                    $a->printer?->connection_type === null ? null : __('enums.printer_connection.'.$a->printer->connection_type->value, [], $locale),
                    __('enums.asset_status.'.$a->status->value, [], $locale), $counter['total'] ?? null,
                    $counter === null ? null : date('d.m.Y', (int) strtotime($counter['read_at'])),
                    $replacement === null ? null : date('d.m.Y', (int) strtotime($replacement['replaced_at'])).' — '.$replacement['cartridge'],
                ];
            }
        }
    }
}
