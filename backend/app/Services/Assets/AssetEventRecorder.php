<?php

namespace App\Services\Assets;

use App\Enums\AssetEventAction;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\User;

/** Writes the asset's history (spec "harakatlar tarixi"). */
class AssetEventRecorder
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function record(Asset $asset, ?User $by, AssetEventAction $action, ?array $old = null, ?array $new = null, ?string $note = null): AssetEvent
    {
        return AssetEvent::create([
            'asset_id' => $asset->id,
            'user_id' => $by?->id,
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
            'note' => $note,
        ]);
    }
}
