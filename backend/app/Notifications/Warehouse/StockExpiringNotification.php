<?php

namespace App\Notifications\Warehouse;

use App\Models\User;
use App\Notifications\Channels\ChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Lots in the recipient's warehouses expire soon (once per lot and date). */
class StockExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  list<string>  $lots  "Material (batch) — warehouse, date" */
    public function __construct(public array $lots, public int $days) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? app(ChannelResolver::class)->for($notifiable, 'stock_expiring') : ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'stock_expiring',
            'title_key' => 'notifications.stock_expiring',
            'params' => ['count' => count($this->lots), 'days' => $this->days, 'lots' => implode('; ', array_slice($this->lots, 0, 20))],
            'url' => '/warehouse?filter[expiring]=1',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : (string) config('app.locale');
        $params = ['count' => count($this->lots), 'days' => $this->days];
        $mail = (new MailMessage)
            ->subject(__('notifications.stock_expiring.subject', $params, $locale))
            ->greeting(__('mail.greeting', [], $locale))
            ->line(__('notifications.stock_expiring.line', $params, $locale));
        foreach (array_slice($this->lots, 0, 50) as $lot) {
            $mail->line($lot);
        }

        return $mail->action(__('notifications.open_warehouse', [], $locale), rtrim((string) config('app.url'), '/').'/warehouse?filter[expiring]=1')
            ->salutation(__('mail.salutation', [], $locale));
    }
}
