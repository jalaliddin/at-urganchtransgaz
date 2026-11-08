<?php

namespace App\Models;

use App\Enums\RequisitionAction;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $requisition_id
 * @property int|null $user_id
 * @property RequisitionAction $action
 * @property string|null $from_status
 * @property string|null $to_status
 * @property string|null $comment
 * @property array<string, mixed>|null $payload
 * @property CarbonImmutable $created_at
 */
class RequisitionHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['requisition_id', 'user_id', 'action', 'from_status', 'to_status', 'comment', 'payload'];

    protected function casts(): array
    {
        return [
            'action' => RequisitionAction::class,
            'payload' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScope(OrganizationScope::class);
    }
}
