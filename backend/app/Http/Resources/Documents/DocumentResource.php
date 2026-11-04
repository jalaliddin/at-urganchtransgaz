<?php

namespace App\Http\Resources\Documents;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Document */
class DocumentResource extends JsonResource
{
    use FormatsDates;

    /** @var list<array<string, mixed>>|null links prepared by the controller (visibility-filtered) */
    public ?array $links = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Document $document */
        $document = $this->resource;

        return [
            'id' => $document->id,
            'organization' => $document->relationLoaded('organization') && $document->organization
                ? ['id' => $document->organization->id, 'name' => $document->organization->name] : null,
            'type' => $document->type->value,
            'number' => $document->number,
            'title' => $document->title,
            'document_date' => $document->document_date?->format('Y-m-d'),
            'description' => $document->description,
            'current_version' => $document->relationLoaded('currentVersion') && $document->currentVersion
                ? self::version($document->currentVersion) : null,
            'versions' => $this->when($document->relationLoaded('versions'),
                fn () => $document->versions->sortByDesc('version')->values()->map(fn (DocumentVersion $v) => self::version($v))->all()),
            'links' => $this->when($this->links !== null, fn () => $this->links),
            'can_manage' => (bool) $request->user()?->can('update', $document),
            'created_at' => $this->iso($document->created_at),
            'updated_at' => $this->iso($document->updated_at),
        ];
    }

    /** @return array<string, mixed> */
    public static function version(DocumentVersion $version): array
    {
        return [
            'id' => $version->id,
            'version' => $version->version,
            'note' => $version->note,
            'file' => [
                'uuid' => $version->file->uuid, 'name' => $version->file->original_name,
                'mime' => $version->file->mime, 'size' => $version->file->size,
            ],
            'uploaded_by' => $version->uploader ? ['id' => $version->uploader->id, 'name' => $version->uploader->name] : null,
            'created_at' => $version->created_at?->toIso8601String(),
        ];
    }
}
