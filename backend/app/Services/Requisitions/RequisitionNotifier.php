<?php

namespace App\Services\Requisitions;

use App\Models\Requisition;
use App\Models\User;
use App\Notifications\Requisitions\RequisitionNotification;
use App\Services\Helpdesk\HelpdeskRecipients;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/** Who hears about which requisition step. The acting user is never notified. */
class RequisitionNotifier
{
    public function __construct(private readonly HelpdeskRecipients $recipients) {}

    /**
     * Organization heads who can approve at the first stage (not the requester, not Super Admins).
     *
     * @return Collection<int, User>
     */
    public function approvers(Requisition $requisition): Collection
    {
        return $this->recipients->withPermission('requisitions.approve_org', $requisition->organization_id)
            ->reject(fn (User $u) => $u->id === $requisition->requester_id || $u->isSuperAdmin())->values();
    }

    /** @return Collection<int, User> */
    public function holders(string $permission, Requisition $requisition): Collection
    {
        return $this->recipients->withPermission($permission, $requisition->organization_id);
    }

    /** @return Collection<int, User> */
    public function requester(Requisition $requisition): Collection
    {
        $user = $requisition->requester_id === null ? null : User::query()->withoutGlobalScopes()->where('is_active', true)->find($requisition->requester_id);

        return collect($user ? [$user] : []);
    }

    /** @param  Collection<int, User>  $users */
    public function send(Requisition $requisition, string $event, Collection $users, User $actor, ?string $comment = null): void
    {
        $to = $users->reject(fn (User $u) => $u->id === $actor->id)->unique('id')->values();
        if ($to->isEmpty()) {
            return;
        }
        $requisition->loadMissing('organization');
        Notification::send($to, new RequisitionNotification(
            $event, $requisition->id, $requisition->number, $requisition->title, $requisition->organization->name, $comment,
        ));
    }
}
