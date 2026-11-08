<?php

namespace App\Models;

use App\Enums\SimEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Event in a SIM card's life. Payloads never hold the ICCID (only its last four digits).
 *
 * @property int $id
 * @property int $sim_card_id
 * @property SimEvent $event
 * @property int|null $organization_id
 * @property array<string, mixed>|null $payload
 * @property int|null $requisition_id
 * @property int|null $user_id
 * @property CarbonImmutable|null $created_at
 * @property-read User|null $user
 */
class SimHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['sim_card_id', 'event', 'organization_id', 'payload', 'requisition_id', 'user_id'];

    protected function casts(): array
    {
        return ['event' => SimEvent::class, 'payload' => 'array', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
