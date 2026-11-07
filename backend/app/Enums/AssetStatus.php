<?php

namespace App\Enums;

enum AssetStatus: string
{
    case InStock = 'in_stock';
    case InUse = 'in_use';
    case InRepair = 'in_repair';
    case Reserve = 'reserve';
    case WrittenOff = 'written_off';
    case Lost = 'lost';

    /**
     * Statuses a user may set directly; the others come from assignment, repair or write-off.
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::InStock, self::Reserve, self::Lost];
    }

    /** No longer on the books or physically gone: cannot be assigned, transferred or repaired. */
    public function isTerminal(): bool
    {
        return $this === self::WrittenOff;
    }

    public function label(?string $locale = null): string
    {
        return __('enums.asset_status.'.$this->value, [], $locale);
    }
}
