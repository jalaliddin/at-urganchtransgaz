<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\Settings\SettingsService;

/**
 * One place that decides delivery channels. In-app (database) is always on;
 * mail when enabled system-wide, the user has an address and has not muted the
 * type. Telegram/SMS channels plug in here later.
 */
class ChannelResolver
{
    /** Notification types a user may mute for e-mail. */
    public const TYPES = [
        'ticket_created', 'ticket_assigned', 'ticket_status_changed', 'ticket_commented',
        'ticket_sla_breached', 'ticket_due_soon', 'export_ready',
    ];

    public function __construct(private readonly SettingsService $settings) {}

    /** @return list<string> */
    public function for(User $user, string $type): array
    {
        $channels = ['database'];
        $muted = (array) (($user->notification_preferences ?? [])['mail_muted'] ?? []);

        if ($this->settings->get('notifications.mail_enabled') && filled($user->email) && ! in_array($type, $muted, true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }
}
