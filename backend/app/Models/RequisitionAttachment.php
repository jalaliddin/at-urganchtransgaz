<?php

namespace App\Models;

use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $requisition_id
 * @property int $file_id
 * @property int|null $uploaded_by
 * @property CarbonImmutable $created_at
 * @property-read StoredFile $file
 */
class RequisitionAttachment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['requisition_id', 'file_id', 'uploaded_by'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<StoredFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'file_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withoutGlobalScope(OrganizationScope::class);
    }
}
