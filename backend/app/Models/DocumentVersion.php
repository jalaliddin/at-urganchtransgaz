<?php

namespace App\Models;

use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $document_id
 * @property int $version
 * @property int $stored_file_id
 * @property string|null $note
 * @property int|null $uploaded_by
 * @property-read StoredFile $file
 */
class DocumentVersion extends Model
{
    protected $fillable = ['document_id', 'version', 'stored_file_id', 'note', 'uploaded_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<StoredFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'stored_file_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withoutGlobalScope(OrganizationScope::class);
    }
}
