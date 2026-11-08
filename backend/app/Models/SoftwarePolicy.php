<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserStamps;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "This title must be installed": for an organization subtree (or all) and an asset category
 * (or the default device categories).
 *
 * @property int $id
 * @property int $software_id
 * @property int|null $organization_id
 * @property int|null $asset_category_id
 * @property string|null $note
 * @property-read Software $software
 * @property-read Organization|null $organization
 * @property-read AssetCategory|null $assetCategory
 */
class SoftwarePolicy extends Model
{
    use Auditable, HasUserStamps;

    protected $fillable = ['software_id', 'organization_id', 'asset_category_id', 'note'];

    /** @return BelongsTo<Software, $this> */
    public function software(): BelongsTo
    {
        return $this->belongsTo(Software::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<AssetCategory, $this> */
    public function assetCategory(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class);
    }
}
