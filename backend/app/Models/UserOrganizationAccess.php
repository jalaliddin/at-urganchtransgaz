<?php

namespace App\Models;

use Database\Factories\UserOrganizationAccessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * One organization a user may see; include_descendants also grants the whole subtree.
 *
 * @property int $id
 * @property int $user_id
 * @property int $organization_id
 * @property bool $include_descendants
 */
class UserOrganizationAccess extends Model
{
    /** @use HasFactory<UserOrganizationAccessFactory> */
    use HasFactory;

    protected $table = 'user_organization_access';

    protected $fillable = ['user_id', 'organization_id', 'include_descendants'];

    protected function casts(): array
    {
        return ['include_descendants' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (UserOrganizationAccess $row): void {
            if (Auth::hasUser()) {
                $row->created_by ??= Auth::id();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
