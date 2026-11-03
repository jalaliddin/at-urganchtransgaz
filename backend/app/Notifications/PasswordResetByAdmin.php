<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** An administrator issued a temporary password for this account. */
class PasswordResetByAdmin extends Notification implements ShouldQueue
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
            'type' => 'password_reset_by_admin',
            'title_key' => 'notifications.password_reset_by_admin',
            'params' => ['actor' => $this->actorName],
            'url' => '/profile',
        ];
    }
}
