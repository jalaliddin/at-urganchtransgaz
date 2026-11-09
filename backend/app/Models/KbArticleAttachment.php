<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $article_id
 * @property int $file_id
 * @property int|null $uploaded_by
 * @property-read StoredFile $file
 */
class KbArticleAttachment extends Model
{
    protected $fillable = ['article_id', 'file_id', 'uploaded_by'];

    /** @return BelongsTo<StoredFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'file_id')->withoutGlobalScopes();
    }
}
