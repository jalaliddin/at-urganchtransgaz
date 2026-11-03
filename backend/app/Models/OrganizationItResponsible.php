<?php

namespace App\Models;

use Database\Factories\OrganizationItResponsibleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $employee_id
 * @property bool $is_primary
 */
class OrganizationItResponsible extends Model
{
    /** @use HasFactory<OrganizationItResponsibleFactory> */
    use HasFactory;

    protected $fillable = ['organization_id', 'employee_id', 'is_primary', 'assigned_at', 'notes'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'assigned_at' => 'date'];
    }

    protected static function booted(): void
    {
        static::creating(function (OrganizationItResponsible $row): void {
            if (Auth::hasUser()) {
                $row->created_by ??= Auth::id();
            }
        });
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
