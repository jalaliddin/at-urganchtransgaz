<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property int $id
 * @property string $name
 * @property string $display_name
 * @property string|null $description
 * @property bool $is_system
 */
class Role extends SpatieRole
{
    use Auditable;

    protected $fillable = ['name', 'guard_name', 'display_name', 'description', 'is_system'];

    /**
     * Adds users_count. Spatie's users() relation resolves the model from the *default*
     * guard (sanctum inside API requests), so count model_has_roles directly.
     *
     * @param  Builder<Role>  $query
     */
    public function scopeWithUsersCount(Builder $query): void
    {
        $query->addSelect(['users_count' => DB::table(config('permission.table_names.model_has_roles'))
            ->selectRaw('count(*)')
            ->whereColumn('role_id', 'roles.id'),
        ]);
    }

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }
}
