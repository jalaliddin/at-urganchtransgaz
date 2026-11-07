<?php

namespace App\Notifications\Assets;

use App\Models\AssetTransfer;
use App\Models\User;
use App\Notifications\Channels\ChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Assets were sent to the recipient's organization and wait to be received. */
class AssetTransferIncomingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $transferId;

    public string $number;

    public string $from;

    public function __construct(AssetTransfer $transfer)
    {
        $this->transferId = $transfer->id;
        $this->number = $transfer->number;
        $this->from = $transfer->fromOrganization->name;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? app(ChannelResolver::class)->for($notifiable, 'asset_transfer_incoming') : ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'asset_transfer_incoming',
            'title_key' => 'notifications.asset_transfer_incoming',
            'params' => ['number' => $this->number, 'from' => $this->from],
            'url' => "/assets/transfers/{$this->transferId}",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : (string) config('app.locale');
        $params = ['number' => $this->number, 'from' => $this->from];

        return (new MailMessage)
            ->subject(__('notifications.asset_transfer_incoming.subject', $params, $locale))
            ->greeting(__('mail.greeting', [], $locale))
            ->line(__('notifications.asset_transfer_incoming.line', $params, $locale))
            ->action(__('notifications.open_transfer', [], $locale), rtrim((string) config('app.url'), '/')."/assets/transfers/{$this->transferId}")
            ->salutation(__('mail.salutation', [], $locale));
    }
}
