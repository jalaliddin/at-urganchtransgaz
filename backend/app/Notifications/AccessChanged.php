<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** The user's roles or organization access were changed by someone else. */
class AccessChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $actorName) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'access_changed',
            'title_key' => 'notifications.access_changed',
            'params' => ['actor' => $this->actorName],
            'url' => '/profile',
        ];
    }
}
