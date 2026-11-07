<?php

namespace App\Http\Controllers\Api\V1\Assets;

use App\Enums\AssetEventAction;
use App\Enums\AssetRelationType;
use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAttachment;
use App\Models\AssetRelation;
use App\Models\User;
use App\Rules\SafeUpload;
use App\Services\Assets\AssetAttachments;
use App\Services\Assets\AssetEventRecorder;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** History, files and CMDB relations of an asset card. */
class AssetDetailController extends Controller
{
    public function __construct(private readonly AssetEventRecorder $events) {}

    public function events(Asset $asset): JsonResponse
    {
        Gate::authorize('view', $asset);
        $rows = $asset->events()->with('user')->orderByDesc('id')->get()->map(fn ($e) => [
            'id' => $e->id,
            'action' => $e->action->value,
            'user' => $e->user ? ['id' => $e->user->id, 'name' => $e->user->name] : null,
            'old_values' => $e->old_values,
            'new_values' => $e->new_values,
            'note' => $e->note,
            'created_at' => $e->created_at->toIso8601String(),
        ]);

        return ApiResponse::data($rows->values());
    }

    public function attachments(Asset $asset): JsonResponse
    {
        Gate::authorize('view', $asset);
        $rows = AssetAttachment::query()->where('asset_id', $asset->id)->with(['file', 'user'])->orderBy('id')->get();

        return ApiResponse::data($rows->map(fn ($a) => $this->attachmentRow($a))->values());
    }

    public function storeAttachments(Request $request, Asset $asset, AssetAttachments $files): JsonResponse
    {
        Gate::authorize('update', $asset);
        $request->validate(['files' => ['required', 'array', 'min:1', 'max:5'], 'files.*' => [new SafeUpload]]);
        /** @var User $user */
        $user = $request->user();
        $created = DB::transaction(fn () => $files->attach($asset, array_values((array) $request->file('files')), $user));

        return ApiResponse::data(array_map(fn ($a) => $this->attachmentRow($a->load(['file', 'user'])), $created), 201);
    }

    public function destroyAttachment(Request $request, Asset $asset, int $attachment, AssetAttachments $files): JsonResponse
    {
        Gate::authorize('update', $asset);
        $row = AssetAttachment::query()->where('asset_id', $asset->id)->with('file')->find($attachment) ?? throw new NotFoundHttpException;
        /** @var User $user */
        $user = $request->user();
        DB::transaction(fn () => $files->delete($asset, $row, $user));

        return ApiResponse::message(__('messages.deleted'));
    }

    public function relations(Asset $asset): JsonResponse
    {
        Gate::authorize('view', $asset);
        $rows = AssetRelation::query()
            ->where(fn ($q) => $q->where('asset_id', $asset->id)->orWhere('related_asset_id', $asset->id))
            ->with(['asset.category', 'related.category'])->orderBy('id')->get()
            ->map(function (AssetRelation $r) use ($asset): ?array {
                $outgoing = $r->asset_id === $asset->id;
                $other = $outgoing ? $r->related : $r->asset;
                if ($other === null || ! request()->user()?->canAccessOrganization($other->organization_id)) {
                    return null;
                }

                return [
                    'id' => $r->id, 'type' => $r->type->value, 'direction' => $outgoing ? 'outgoing' : 'incoming', 'note' => $r->note,
                    'asset' => [
                        'id' => $other->id, 'inventory_number' => $other->inventory_number, 'name' => $other->name,
                        'category' => $other->category?->localizedName(), 'hostname' => $other->hostname, 'ip_address' => $other->ip_address,
                        'status' => $other->status->value,
                    ],
                ];
            })->filter()->values();

        return ApiResponse::data($rows);
    }

    public function storeRelation(Request $request, Asset $asset): JsonResponse
    {
        Gate::authorize('update', $asset);
        $data = $request->validate([
            'related_asset_id' => ['required', 'integer'],
            'type' => ['required', Rule::enum(AssetRelationType::class)],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $other = Asset::query()->find((int) $data['related_asset_id']);
        if ($other === null || $other->id === $asset->id) {
            throw ValidationException::withMessages(['related_asset_id' => __('messages.asset_relation_invalid')]);
        }
        $exists = AssetRelation::query()->where('type', $data['type'])->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('asset_id', $asset->id)->where('related_asset_id', $other->id))
            ->orWhere(fn ($q) => $q->where('asset_id', $other->id)->where('related_asset_id', $asset->id)))->exists();
        if ($exists) {
            throw new DomainConflictException(__('messages.asset_relation_exists'));
        }
        /** @var User $user */
        $user = $request->user();
        $relation = DB::transaction(function () use ($asset, $other, $data, $user): AssetRelation {
            $relation = AssetRelation::create([
                'asset_id' => $asset->id, 'related_asset_id' => $other->id, 'type' => $data['type'],
                'note' => $data['note'] ?? null, 'created_by' => $user->id,
            ]);
            $this->events->record($asset, $user, AssetEventAction::RelationAdded, new: ['type' => $data['type'], 'asset' => $other->inventory_number]);
            $this->events->record($other, $user, AssetEventAction::RelationAdded, new: ['type' => $data['type'], 'asset' => $asset->inventory_number]);

            return $relation;
        });

        return ApiResponse::data(['id' => $relation->id], 201);
    }

    public function destroyRelation(Request $request, Asset $asset, int $relation): JsonResponse
    {
        Gate::authorize('update', $asset);
        $row = AssetRelation::query()
            ->where(fn ($q) => $q->where('asset_id', $asset->id)->orWhere('related_asset_id', $asset->id))
            ->find($relation) ?? throw new NotFoundHttpException;
        /** @var User $user */
        $user = $request->user();
        DB::transaction(function () use ($row, $asset, $user): void {
            $row->delete();
            $this->events->record($asset, $user, AssetEventAction::RelationRemoved, old: ['type' => $row->type->value]);
        });

        return ApiResponse::message(__('messages.deleted'));
    }

    /** @return array<string, mixed> */
    private function attachmentRow(AssetAttachment $a): array
    {
        return [
            'id' => $a->id,
            'file' => ['uuid' => $a->file->uuid, 'name' => $a->file->original_name, 'mime' => $a->file->mime, 'size' => $a->file->size, 'is_image' => $a->file->isImage()],
            'user' => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name] : null,
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }
}
