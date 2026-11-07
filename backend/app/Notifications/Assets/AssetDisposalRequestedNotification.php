<?php

namespace App\Notifications\Assets;

use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Models\User;
use App\Notifications\Channels\ChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A write-off waits for the recipient's decision. */
class AssetDisposalRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $disposalId;

    public string $inventoryNumber;

    public string $assetName;

    public function __construct(AssetDisposal $disposal, Asset $asset)
    {
        $this->disposalId = $disposal->id;
        $this->inventoryNumber = $asset->inventory_number;
        $this->assetName = $asset->name;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? app(ChannelResolver::class)->for($notifiable, 'asset_disposal_requested') : ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'asset_disposal_requested',
            'title_key' => 'notifications.asset_disposal_requested',
            'params' => ['number' => $this->inventoryNumber, 'name' => $this->assetName],
            'url' => '/assets/disposals',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : (string) config('app.locale');
        $params = ['number' => $this->inventoryNumber, 'name' => $this->assetName];

        return (new MailMessage)
            ->subject(__('notifications.asset_disposal_requested.subject', $params, $locale))
            ->greeting(__('mail.greeting', [], $locale))
            ->line(__('notifications.asset_disposal_requested.line', $params, $locale))
            ->action(__('notifications.open_disposals', [], $locale), rtrim((string) config('app.url'), '/').'/assets/disposals')
            ->salutation(__('mail.salutation', [], $locale));
    }
}
