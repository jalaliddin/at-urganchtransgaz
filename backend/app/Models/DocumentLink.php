<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $document_id
 * @property string $linkable_type morph alias (e.g. "ticket")
 * @property int $linkable_id
 */
class DocumentLink extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['document_id', 'linkable_type', 'linkable_id', 'created_by'];

    protected function casts(): array
    {
        return ['linkable_id' => 'integer'];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
