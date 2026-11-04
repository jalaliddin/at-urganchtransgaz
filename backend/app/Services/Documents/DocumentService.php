<?php

namespace App\Services\Documents;

use App\Exceptions\DomainConflictException;
use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\DocumentVersion;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\Files\FileStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocumentService
{
    public function __construct(
        private readonly FileStore $files,
        private readonly DocumentLinkTargets $targets,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function create(array $data, ?UploadedFile $file, User $by): Document
    {
        return DB::transaction(function () use ($data, $file, $by): Document {
            $document = Document::create($data);
            if ($file !== null) {
                $this->addVersion($document, $file, null, $by);
            }

            return $document;
        });
    }

    public function addVersion(Document $document, UploadedFile $file, ?string $note, User $by): DocumentVersion
    {
        return DB::transaction(function () use ($document, $file, $note, $by): DocumentVersion {
            $locked = Document::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($document->id);
            $next = (int) $locked->versions()->max('version') + 1;
            $stored = $this->files->store($file, $document->organization_id, $by);
            $version = $document->versions()->create([
                'version' => $next, 'stored_file_id' => $stored->id, 'note' => $note, 'uploaded_by' => $by->id,
            ]);
            $document->forceFill(['current_version_id' => $version->id])->save();

            return $version;
        });
    }

    public function link(Document $document, string $type, int $id, User $by): DocumentLink
    {
        $target = $this->targets->find($type, $id, $by) ?? throw new NotFoundHttpException;
        $exists = $document->links()->where('linkable_type', $type)->where('linkable_id', $target->getKey())->exists();
        if ($exists) {
            throw new DomainConflictException(__('messages.document_link_exists'));
        }

        return $document->links()->create([
            'linkable_type' => $type, 'linkable_id' => $target->getKey(), 'created_by' => $by->id,
        ]);
    }

    /** FileAccess resolver: null for files that are not document versions. */
    public function canDownload(User $user, StoredFile $file): ?bool
    {
        $version = DocumentVersion::query()->with('document')->where('stored_file_id', $file->id)->first();
        if ($version === null) {
            return null;
        }

        return $version->document !== null && $user->can('view', $version->document);
    }
}
