<?php

namespace App\Models;

use App\Enums\ChangeEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $change_id
 * @property ChangeEvent $event
 * @property array<string, mixed>|null $payload
 * @property string|null $comment
 * @property int|null $user_id
 * @property CarbonImmutable $created_at
 * @property-read User|null $user
 */
class ChangeHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['change_id', 'event', 'payload', 'comment', 'user_id'];

    protected function casts(): array
    {
        return ['event' => ChangeEvent::class, 'payload' => 'array', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }
}
