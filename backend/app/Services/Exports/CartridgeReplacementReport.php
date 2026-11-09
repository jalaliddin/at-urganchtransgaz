<?php

namespace App\Services\Exports;

use App\Models\User;
use App\Services\Printers\CartridgeReplacements;

/** Cartridge replacement journal of the user's organizations. */
class CartridgeReplacementReport implements ReportDefinition
{
    public function __construct(private readonly CartridgeReplacements $replacements) {}

    public function permission(): string
    {
        return 'printers.export';
    }

    public function filterNames(): array
    {
        return CartridgeReplacements::FILTERS;
    }

    public function title(string $locale): string
    {
        return __('exports.cartridge_replacements.title', [], $locale);
    }

    public function columns(string $locale): array
    {
        return array_map(fn (string $k) => __("exports.cartridge_replacements.columns.{$k}", [], $locale), [
            'replaced_at', 'printer', 'organization', 'cartridge', 'part_number', 'source', 'warehouse', 'document', 'cost', 'counter',
            'performed_by', 'cancelled',
        ]);
    }

    public function rows(User $user, array $filters, string $locale): iterable
    {
        $query = $this->replacements->journal($user, $filters)
            ->with(['organization', 'asset', 'cartridge', 'warehouse', 'stockDocument', 'performer', 'counter'])
            ->orderByDesc('replaced_at')->orderByDesc('id');
        foreach ($query->lazy(500) as $r) {
            yield [
                $r->replaced_at->format('d.m.Y'), $r->asset->inventory_number.' — '.$r->asset->name, $r->organization->name, $r->cartridge->name,
                $r->cartridge->part_number, __('enums.replacement_source.'.$r->source->value, [], $locale), $r->warehouse?->name,
                $r->stockDocument?->number, $r->stockDocument?->total_amount, $r->counter?->total, $r->performer?->name,
                $r->cancelled_at === null ? null : trim(__('exports.yes', [], $locale).': '.($r->cancel_reason ?? '')),
            ];
        }
    }
}
