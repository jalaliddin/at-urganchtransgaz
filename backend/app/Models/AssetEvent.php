<?php

namespace App\Models;

use App\Enums\AssetEventAction;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only history entry of an asset.
 *
 * @property int $id
 * @property int $asset_id
 * @property int|null $user_id
 * @property AssetEventAction $action
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $note
 */
class AssetEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['asset_id', 'user_id', 'action', 'old_values', 'new_values', 'note'];

    protected function casts(): array
    {
        return [
            'action' => AssetEventAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScope(OrganizationScope::class);
    }
}
