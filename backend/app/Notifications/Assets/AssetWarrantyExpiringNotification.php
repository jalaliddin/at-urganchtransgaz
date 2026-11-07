<?php

namespace App\Notifications\Assets;

use App\Models\User;
use App\Notifications\Channels\ChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Warranty of some assets in the recipient's organization ends soon. */
class AssetWarrantyExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  list<string>  $inventoryNumbers */
    public function __construct(public string $organization, public array $inventoryNumbers, public int $days) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? app(ChannelResolver::class)->for($notifiable, 'asset_warranty_expiring') : ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'asset_warranty_expiring',
            'title_key' => 'notifications.asset_warranty_expiring',
            'params' => [
                'organization' => $this->organization, 'count' => count($this->inventoryNumbers), 'days' => $this->days,
                'assets' => implode(', ', array_slice($this->inventoryNumbers, 0, 20)),
            ],
            'url' => '/assets?filter[warranty]=expiring',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : (string) config('app.locale');
        $params = ['organization' => $this->organization, 'count' => count($this->inventoryNumbers), 'days' => $this->days];

        return (new MailMessage)
            ->subject(__('notifications.asset_warranty_expiring.subject', $params, $locale))
            ->greeting(__('mail.greeting', [], $locale))
            ->line(__('notifications.asset_warranty_expiring.line', $params, $locale))
            ->line(implode(', ', array_slice($this->inventoryNumbers, 0, 50)))
            ->action(__('notifications.open_assets', [], $locale), rtrim((string) config('app.url'), '/').'/assets?filter[warranty]=expiring')
            ->salutation(__('mail.salutation', [], $locale));
    }
}
