<?php

namespace App\Models;

use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who used a SIM card and when (employee and/or department of the holder organization).
 *
 * @property int $id
 * @property int $sim_card_id
 * @property int $organization_id
 * @property int|null $employee_id
 * @property int|null $department_id
 * @property CarbonImmutable $assigned_at
 * @property CarbonImmutable|null $returned_at
 * @property int|null $assigned_by
 * @property int|null $returned_by
 * @property string|null $note
 * @property-read Employee|null $employee
 * @property-read Department|null $department
 * @property-read SimCard $simCard
 */
class SimAssignment extends Model
{
    protected $fillable = ['sim_card_id', 'organization_id', 'employee_id', 'department_id', 'assigned_at', 'returned_at', 'assigned_by', 'returned_by', 'note'];

    protected function casts(): array
    {
        return ['assigned_at' => 'immutable_date', 'returned_at' => 'immutable_date'];
    }

    /** @return BelongsTo<SimCard, $this> */
    public function simCard(): BelongsTo
    {
        return $this->belongsTo(SimCard::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withoutGlobalScope(OrganizationScope::class);
    }
}
