<?php

namespace App\Models;

use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One period during which an asset was handed to an employee (open while returned_at is null).
 *
 * @property int $id
 * @property int $asset_id
 * @property int $organization_id
 * @property int|null $employee_id
 * @property CarbonImmutable $assigned_at
 * @property int|null $assigned_by
 * @property string|null $note
 * @property CarbonImmutable|null $returned_at
 * @property int|null $returned_by
 * @property string|null $return_note
 */
class AssetAssignment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'asset_id', 'organization_id', 'employee_id', 'assigned_at', 'assigned_by', 'note',
        'returned_at', 'returned_by', 'return_note',
    ];

    protected function casts(): array
    {
        return ['assigned_at' => 'immutable_datetime', 'returned_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<User, $this> */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by')->withoutGlobalScope(OrganizationScope::class);
    }
}
