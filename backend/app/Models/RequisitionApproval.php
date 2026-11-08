<?php

namespace App\Models;

use App\Enums\RequisitionDecision;
use App\Enums\RequisitionStage;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $requisition_id
 * @property RequisitionStage $stage
 * @property RequisitionDecision $decision
 * @property int|null $user_id
 * @property string|null $comment
 * @property CarbonImmutable $created_at
 */
class RequisitionApproval extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['requisition_id', 'stage', 'decision', 'user_id', 'comment'];

    protected function casts(): array
    {
        return [
            'stage' => RequisitionStage::class,
            'decision' => RequisitionDecision::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScope(OrganizationScope::class);
    }
}
