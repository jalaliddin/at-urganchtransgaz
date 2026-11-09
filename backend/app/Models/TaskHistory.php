<?php

namespace App\Models;

use App\Enums\TaskEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $task_id
 * @property TaskEvent $event
 * @property array<string, mixed>|null $payload
 * @property int|null $user_id
 * @property CarbonImmutable|null $created_at
 * @property-read User|null $user
 */
class TaskHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['task_id', 'event', 'payload', 'user_id'];

    protected function casts(): array
    {
        return ['event' => TaskEvent::class, 'payload' => 'array', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }
}
