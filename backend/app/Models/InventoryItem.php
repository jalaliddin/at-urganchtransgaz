<?php

namespace App\Models;

use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Expected asset (from the snapshot) or an unexpected scanned code.
 *
 * @property int $id
 * @property int $session_id
 * @property int|null $asset_id
 * @property string $inventory_number
 * @property bool $expected
 * @property CarbonImmutable|null $found_at
 * @property int|null $scanned_by
 * @property string|null $note
 */
class InventoryItem extends Model
{
    protected $fillable = ['session_id', 'asset_id', 'inventory_number', 'expected', 'found_at', 'scanned_by', 'note'];

    protected function casts(): array
    {
        return ['expected' => 'boolean', 'found_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<User, $this> */
    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by')->withoutGlobalScope(OrganizationScope::class);
    }
}
