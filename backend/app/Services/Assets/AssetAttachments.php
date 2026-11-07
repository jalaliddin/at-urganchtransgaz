<?php

namespace App\Services\Assets;

use App\Enums\AssetEventAction;
use App\Models\Asset;
use App\Models\AssetAttachment;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\Files\FileStore;
use Illuminate\Http\UploadedFile;

/** Photos, acts and invoices on the asset card (private disk). */
class AssetAttachments
{
    public function __construct(private readonly FileStore $files, private readonly AssetEventRecorder $events) {}

    /**
     * @param  list<UploadedFile>  $uploads
     * @return list<AssetAttachment>
     */
    public function attach(Asset $asset, array $uploads, User $by): array
    {
        $created = [];
        foreach ($uploads as $upload) {
            $file = $this->files->store($upload, $asset->organization_id, $by);
            $created[] = AssetAttachment::create(['asset_id' => $asset->id, 'stored_file_id' => $file->id, 'user_id' => $by->id]);
        }
        $this->events->record($asset, $by, AssetEventAction::AttachmentAdded, new: [
            'files' => array_map(fn (AssetAttachment $a) => $a->file->original_name, $created),
        ]);

        return $created;
    }

    public function delete(Asset $asset, AssetAttachment $attachment, User $by): void
    {
        $name = $attachment->file->original_name;
        $this->files->delete($attachment->file);
        $this->events->record($asset, $by, AssetEventAction::AttachmentRemoved, old: ['files' => [$name]]);
    }

    /** FileAccess resolver: asset files follow the asset's visibility. */
    public function canDownload(User $user, StoredFile $file): ?bool
    {
        $assetId = AssetAttachment::query()->where('stored_file_id', $file->id)->value('asset_id');
        if ($assetId === null) {
            return null;
        }
        $asset = Asset::query()->withoutGlobalScopes()->find($assetId);

        return $asset !== null && $user->can('view', $asset);
    }
}
