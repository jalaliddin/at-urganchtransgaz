<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserStamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mobile operator, internet provider or other IT service vendor (global reference data).
 *
 * @property int $id
 * @property string $name
 * @property string|null $tin
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property bool $is_mobile_operator
 * @property bool $is_active
 * @property string|null $note
 */
class ServiceProvider extends Model
{
    use Auditable, HasUserStamps;

    protected $fillable = ['name', 'tin', 'phone', 'email', 'address', 'is_mobile_operator', 'is_active', 'note'];

    protected function casts(): array
    {
        return ['is_mobile_operator' => 'boolean', 'is_active' => 'boolean'];
    }

    /** @return HasMany<SimCard, $this> */
    public function simCards(): HasMany
    {
        return $this->hasMany(SimCard::class, 'provider_id');
    }

    /** @return HasMany<ItService, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(ItService::class, 'provider_id');
    }

    /** @return HasMany<ServiceContract, $this> */
    public function contracts(): HasMany
    {
        return $this->hasMany(ServiceContract::class, 'provider_id');
    }
}
