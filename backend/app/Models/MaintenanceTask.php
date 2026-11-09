<?php

namespace App\Models;

use App\Enums\MaintenanceResult;
use App\Enums\MaintenanceTaskStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\ScopedToUserOrganizations;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scheduled service of one device (design M2).
 *
 * @property int $id
 * @property int $plan_id
 * @property int $organization_id
 * @property int $asset_id
 * @property CarbonImmutable $due_on
 * @property MaintenanceTaskStatus $status
 * @property string|null $open_key
 * @property CarbonImmutable|null $completed_on
 * @property int|null $completed_by
 * @property MaintenanceResult|null $result
 * @property list<array{step: string, done: bool, note: string|null}>|null $checklist
 * @property string|null $note
 * @property CarbonImmutable $created_at
 * @property-read MaintenancePlan $plan
 * @property-read Asset $asset
 * @property-read User|null $completer
 */
class MaintenanceTask extends Model
{
    use BelongsToOrganization, ScopedToUserOrganizations;

    protected $fillable = ['plan_id', 'organization_id', 'asset_id', 'due_on', 'status', 'open_key'];

    protected function casts(): array
    {
        return [
            'due_on' => 'immutable_date',
            'status' => MaintenanceTaskStatus::class,
            'completed_on' => 'immutable_date',
            'result' => MaintenanceResult::class,
            'checklist' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<MaintenancePlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'plan_id')->withoutGlobalScopes();
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<User, $this> */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by')->withoutGlobalScopes();
    }
}
