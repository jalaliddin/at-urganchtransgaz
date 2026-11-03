<?php

namespace App\Models;

use App\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only audit record. Updating or deleting through Eloquent throws.
 *
 * @property int $id
 * @property AuditEvent $event
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property int|null $user_id
 * @property string|null $user_label
 * @property int|null $organization_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $ip_address
 * @property string|null $http_method
 * @property string|null $url
 * @property string|null $request_id
 * @property Carbon $created_at
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'event', 'auditable_type', 'auditable_id', 'user_id', 'user_label', 'organization_id',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'http_method', 'url', 'request_id',
    ];

    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit log entries cannot be deleted.'));
    }

    /**
     * Rows the user may read: everything for unrestricted users, otherwise rows of
     * accessible organizations (rows without an organization stay hidden).
     *
     * @param  Builder<AuditLog>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $scope = $user->accessScope();
        if ($scope->isUnrestricted()) {
            return;
        }

        $ids = $scope->organizationIds();
        $ids === [] ? $query->whereRaw('1 = 0') : $query->whereIn('organization_id', $ids);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes()->withTrashed();
    }
}
