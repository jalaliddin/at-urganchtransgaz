<?php

namespace App\Models;

use App\Enums\LicenseType;
use App\Enums\SoftwareCategory;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserStamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catalog title (also antivirus/EDR products).
 *
 * @property int $id
 * @property string $name
 * @property string $normalized_name
 * @property string|null $vendor
 * @property SoftwareCategory $category
 * @property LicenseType $license_type
 * @property bool $is_antivirus
 * @property string|null $description
 * @property bool $is_active
 */
class Software extends Model
{
    use Auditable, HasUserStamps;

    protected $table = 'software';

    protected $fillable = ['name', 'vendor', 'category', 'license_type', 'is_antivirus', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'category' => SoftwareCategory::class,
            'license_type' => LicenseType::class,
            'is_antivirus' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Software $software): void {
            $software->normalized_name = self::normalize($software->name);
        });
    }

    /** "  Microsoft   Office " → "microsoft office" (matching key for agent/CSV names). */
    public static function normalize(string $name): string
    {
        return mb_substr(mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name))), 0, 150);
    }

    /** @return HasMany<SoftwareInstallation, $this> */
    public function installations(): HasMany
    {
        return $this->hasMany(SoftwareInstallation::class);
    }

    /** @return HasMany<SoftwareLicense, $this> */
    public function licenses(): HasMany
    {
        return $this->hasMany(SoftwareLicense::class);
    }
}
