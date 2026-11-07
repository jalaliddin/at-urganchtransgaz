<?php

namespace App\Services\Documents;

use App\Models\Asset;
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
    public const TYPES = ['ticket', 'asset'];

    public function find(string $type, int $id, User $user): ?Model
    {
        return match ($type) {
            'ticket' => Ticket::query()->visibleTo($user)->find($id),
            'asset' => $user->can('assets.view') ? Asset::query()->visibleTo($user)->find($id) : null,
            default => null,
        };
    }

    /** @return array<string, mixed> */
    public function summary(Model $target): array
    {
        return match (true) {
            $target instanceof Ticket => ['id' => $target->id, 'number' => $target->number, 'title' => $target->title],
            $target instanceof Asset => ['id' => $target->id, 'number' => $target->inventory_number, 'title' => $target->name],
            default => ['id' => $target->getKey()],
        };
    }
}
