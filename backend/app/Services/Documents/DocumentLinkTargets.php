<?php

namespace App\Services\Documents;

use App\Http\Resources\Documents\DocumentResource;
use App\Models\Asset;
use App\Models\Document;
use App\Models\ServiceContract;
use App\Models\SimCard;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Objects a document can be linked to, by morph alias. Each resolver returns the
 * object only when the user may see it. Later phases (assets, contracts …) add
 * their types here.
 */
class DocumentLinkTargets
{
    public const TYPES = ['ticket', 'asset', 'service_contract', 'sim_card'];

    public function find(string $type, int $id, User $user): ?Model
    {
        return match ($type) {
            'ticket' => Ticket::query()->visibleTo($user)->find($id),
            'asset' => $user->can('assets.view') ? Asset::query()->visibleTo($user)->find($id) : null,
            'service_contract' => $user->can('services.use') ? $this->inScope(ServiceContract::query()->withoutGlobalScopes()->find($id), $user) : null,
            'sim_card' => $user->can('sim.use') ? $this->inScope(SimCard::query()->withoutGlobalScopes()->find($id), $user) : null,
            default => null,
        };
    }

    /** @return array<string, mixed> */
    public function summary(Model $target): array
    {
        return match (true) {
            $target instanceof Ticket => ['id' => $target->id, 'number' => $target->number, 'title' => $target->title],
            $target instanceof Asset => ['id' => $target->id, 'number' => $target->inventory_number, 'title' => $target->name],
            $target instanceof ServiceContract => ['id' => $target->id, 'number' => $target->number, 'title' => $target->subject],
            $target instanceof SimCard => ['id' => $target->id, 'number' => $target->phone_number, 'title' => $target->tariff],
            default => ['id' => $target->getKey()],
        };
    }

    /**
     * Documents linked to an object the caller has already checked; empty without documents.view.
     *
     * @return mixed list of DocumentResource arrays
     */
    public function linkedDocuments(string $type, int $id, User $user): mixed
    {
        if (! $user->can('documents.view')) {
            return [];
        }
        $documents = Document::query()
            ->whereHas('links', fn ($q) => $q->where('linkable_type', $type)->where('linkable_id', $id))
            ->with(['organization', 'currentVersion.file', 'currentVersion.uploader'])->orderByDesc('document_date')->get();

        return DocumentResource::collection($documents);
    }

    /**
     * @template T of ServiceContract|SimCard
     *
     * @param  T|null  $model
     * @return T|null
     */
    private function inScope(ServiceContract|SimCard|null $model, User $user): ServiceContract|SimCard|null
    {
        return $model !== null && $user->canAccessOrganization($model->organization_id) ? $model : null;
    }
}
