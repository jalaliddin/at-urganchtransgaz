<?php

namespace App\Models;

use App\Enums\OrganizationType;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUserStamps;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Node of the organization tree. `path` is a materialized path including the node
 * itself (/1/5/12/) so a whole subtree is one indexed LIKE query.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $path
 * @property int $depth
 * @property OrganizationType $type
 * @property string $name
 * @property bool $is_active
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use BelongsToOrganization, HasFactory, HasUserStamps, SoftDeletes;

    protected $fillable = [
        'parent_id', 'type', 'name', 'short_name', 'code', 'tin', 'address',
        'phone', 'email', 'head_name', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrganizationType::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'depth' => 'integer',
        ];
    }

    /** An organization is scoped by its own id. */
    public static function organizationColumn(): string
    {
        return 'id';
    }

    protected static function booted(): void
    {
        static::created(function (Organization $organization): void {
            $parent = $organization->parent_id
                ? static::withoutGlobalScopes()->withTrashed()->findOrFail($organization->parent_id)
                : null;

            $organization->path = ($parent->path ?? '/').$organization->id.'/';
            $organization->depth = $parent ? $parent->depth + 1 : 0;
            $organization->saveQuietly();
        });
    }

    /** @return BelongsTo<Organization, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'parent_id');
    }

    /** @return HasMany<Organization, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Organization::class, 'parent_id');
    }

    /** @return HasMany<Department, $this> */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /** @return HasMany<Employee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /** @return HasMany<OrganizationItResponsible, $this> */
    public function itResponsibles(): HasMany
    {
        return $this->hasMany(OrganizationItResponsible::class);
    }

    /**
     * This organization and every descendant (current and future ones, by path).
     *
     * @return Builder<Organization>
     */
    public function descendantsAndSelf(): Builder
    {
        return Organization::query()->where('path', 'like', $this->path.'%');
    }
}
