<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A repair of an asset (open while finished_at is null).
 *
 * @property int $id
 * @property int $asset_id
 * @property int|null $ticket_id
 * @property MaintenanceStatus $status
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $finished_at
 * @property string|null $vendor
 * @property string $description
 * @property string|null $result
 * @property string|null $cost
 */
class AssetMaintenance extends Model
{
    protected $fillable = ['asset_id', 'ticket_id', 'status', 'started_at', 'vendor', 'description', 'created_by'];

    protected function casts(): array
    {
        return [
            'status' => MaintenanceStatus::class,
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'cost' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withoutGlobalScope(OrganizationScope::class);
    }
}
