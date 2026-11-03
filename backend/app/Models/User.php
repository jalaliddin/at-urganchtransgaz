<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUserStamps;
use App\Notifications\ResetPasswordLink;
use App\Services\Settings\SettingsService;
use App\Support\Access\AccessScope;
use App\Support\Access\OrganizationAccessResolver;
use App\Support\Permissions\Roles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $username
 * @property string $name
 * @property string|null $email
 * @property int|null $organization_id
 * @property int|null $employee_id
 * @property string $locale
 * @property bool $is_active
 * @property bool $must_change_password
 * @property Carbon|null $password_changed_at
 * @property Carbon|null $last_login_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, BelongsToOrganization, HasApiTokens, HasFactory, HasRoles, HasUserStamps, Notifiable, SoftDeletes;

    protected $fillable = [
        'username', 'name', 'email', 'organization_id', 'employee_id', 'password', 'locale',
        'is_active', 'must_change_password', 'password_changed_at',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
        ];
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

    /** @return HasMany<UserOrganizationAccess, $this> */
    public function organizationAccess(): HasMany
    {
        return $this->hasMany(UserOrganizationAccess::class);
    }

    public function accessScope(): AccessScope
    {
        return app(OrganizationAccessResolver::class)->resolve($this);
    }

    public function canAccessOrganization(?int $organizationId): bool
    {
        return $this->accessScope()->allows($organizationId);
    }

    /** Password older than security.password_expires_days (0 = never expires). */
    public function passwordExpired(): bool
    {
        $days = (int) app(SettingsService::class)->get('security.password_expires_days');

        return $days > 0
            && ($this->password_changed_at === null || $this->password_changed_at->lt(now()->subDays($days)));
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordLink($token));
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Roles::SUPER_ADMIN);
    }
}
