<?php

namespace App\Notifications\Warehouse;

use App\Models\User;
use App\Notifications\Channels\ChannelResolver;
use App\Support\Decimal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A material fell below its minimum in a warehouse (once per episode). */
class StockLowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $warehouse, public string $material, public string $quantity, public string $minimum,
        public string $unit, public int $warehouseId, public int $materialId,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? app(ChannelResolver::class)->for($notifiable, 'stock_low') : ['database'];
    }

    /** @return array<string, mixed> */
    private function params(): array
    {
        return ['warehouse' => $this->warehouse, 'material' => $this->material, 'quantity' => Decimal::plain($this->quantity),
            'minimum' => Decimal::plain($this->minimum), 'unit' => $this->unit];
    }

    private function url(): string
    {
        return "/warehouse?filter[warehouse]={$this->warehouseId}&filter[material]={$this->materialId}";
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'stock_low', 'title_key' => 'notifications.stock_low', 'params' => $this->params(), 'url' => $this->url()];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : (string) config('app.locale');

        return (new MailMessage)
            ->subject(__('notifications.stock_low.subject', $this->params(), $locale))
            ->greeting(__('mail.greeting', [], $locale))
            ->line(__('notifications.stock_low.line', $this->params(), $locale))
            ->action(__('notifications.open_warehouse', [], $locale), rtrim((string) config('app.url'), '/').$this->url())
            ->salutation(__('mail.salutation', [], $locale));
    }
}
