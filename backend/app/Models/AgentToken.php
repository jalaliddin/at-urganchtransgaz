<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $agent_id
 * @property string $token_hash
 * @property string $prefix
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $created_at
 * @property-read DeviceAgent $agent
 */
class AgentToken extends Model
{
    public const UPDATED_AT = null;

    protected $hidden = ['token_hash'];

    protected $fillable = ['agent_id', 'token_hash', 'prefix', 'created_by'];

    protected function casts(): array
    {
        return ['last_used_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<DeviceAgent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(DeviceAgent::class, 'agent_id');
    }
}
