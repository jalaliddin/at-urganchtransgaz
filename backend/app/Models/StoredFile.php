<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file on the private disk. Owners (ticket attachments, document versions,
 * exports) decide who may download it — see FileAccess.
 *
 * @property int $id
 * @property string $uuid
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property string $extension
 * @property int $size
 * @property string $sha256
 * @property int|null $organization_id
 * @property int|null $uploaded_by
 */
class StoredFile extends Model
{
    protected $fillable = [
        'uuid', 'disk', 'path', 'original_name', 'mime', 'extension', 'size', 'sha256',
        'organization_id', 'uploaded_by',
    ];

    protected $hidden = ['disk', 'path', 'sha256'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return in_array($this->mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
    }
}
