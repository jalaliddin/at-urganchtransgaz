<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUserStamps;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Registry document (contract, act, invoice …) with file versions and links to
 * the objects it concerns. Access follows the owning organization.
 *
 * @property int $id
 * @property int $organization_id
 * @property DocumentType $type
 * @property string|null $number
 * @property string $title
 * @property CarbonImmutable|null $document_date
 * @property string|null $description
 * @property int|null $current_version_id
 * @property-read DocumentVersion|null $currentVersion
 */
class Document extends Model
{
    use Auditable, BelongsToOrganization, HasUserStamps, SoftDeletes;

    protected $fillable = ['organization_id', 'type', 'number', 'title', 'document_date', 'description'];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'document_date' => 'immutable_date',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return HasMany<DocumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    /** @return HasMany<DocumentLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(DocumentLink::class);
    }
}
