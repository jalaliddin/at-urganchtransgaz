<?php

namespace App\Models;

use App\Enums\InventoryStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Physical count of an organization's assets (expected list frozen at start).
 *
 * @property int $id
 * @property int $organization_id
 * @property bool $include_descendants
 * @property string $name
 * @property InventoryStatus $status
 * @property string|null $note
 * @property int|null $started_by
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $closed_at
 * @property int $expected_count
 * @property int $found_count
 * @property int $missing_count
 * @property int $unexpected_count
 */
class InventorySession extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'include_descendants', 'name', 'status', 'note', 'started_by', 'started_at'];

    protected function casts(): array
    {
        return [
            'status' => InventoryStatus::class,
            'include_descendants' => 'boolean',
            'started_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'expected_count' => 'integer',
            'found_count' => 'integer',
            'missing_count' => 'integer',
            'unexpected_count' => 'integer',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return HasMany<InventoryItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by')->withoutGlobalScope(OrganizationScope::class);
    }
}
