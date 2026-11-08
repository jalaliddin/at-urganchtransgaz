<?php

namespace App\Models;

use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Inventory agent (or integration) bound to one device.
 *
 * @property int $id
 * @property int $asset_id
 * @property string $name
 * @property string|null $platform
 * @property string|null $agent_version
 * @property bool $is_active
 * @property CarbonImmutable|null $last_checkin_at
 * @property string|null $last_ip
 * @property-read Asset $asset
 */
class DeviceAgent extends Model
{
    protected $fillable = ['asset_id', 'name', 'platform', 'agent_version', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_checkin_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return HasMany<AgentToken, $this> */
    public function tokens(): HasMany
    {
        return $this->hasMany(AgentToken::class, 'agent_id');
    }

    /** @return HasMany<AgentCheckin, $this> */
    public function checkins(): HasMany
    {
        return $this->hasMany(AgentCheckin::class, 'agent_id');
    }
}
