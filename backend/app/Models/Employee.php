<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use App\Models\Concerns\HasUserStamps;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $department_id
 * @property string $last_name
 * @property string $first_name
 * @property string|null $middle_name
 * @property EmployeeStatus $status
 * @property-read string $full_name
 */
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, HasUserStamps, SoftDeletes;

    protected $fillable = [
        'organization_id', 'department_id', 'last_name', 'first_name', 'middle_name', 'position',
        'personnel_number', 'phone', 'internal_phone', 'email', 'status', 'hired_at', 'dismissed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmployeeStatus::class,
            'hired_at' => 'date',
            'dismissed_at' => 'date',
        ];
    }

    /**
     * "Last First Middle" — the order used in Uzbek official documents.
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim(implode(' ', array_filter([
            $this->last_name, $this->first_name, $this->middle_name,
        ]))));
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return HasOne<User, $this> */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }
}
