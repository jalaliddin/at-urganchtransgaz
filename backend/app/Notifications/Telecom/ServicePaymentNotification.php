<?php

namespace App\Notifications\Telecom;

use App\Models\User;
use App\Notifications\Channels\ChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A service payment is due soon or overdue (once per invoice, event and due date). */
class ServicePaymentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  'due_soon'|'overdue'  $event */
    public function __construct(
        public string $event,
        public int $invoiceId,
        public string $serviceName,
        public string $provider,
        public string $organization,
        public string $amount,
        public string $date,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? app(ChannelResolver::class)->for($notifiable, 'service_payment_due') : ['database'];
    }

    /** @return array<string, string> */
    private function params(): array
    {
        return ['service' => $this->serviceName, 'provider' => $this->provider, 'organization' => $this->organization, 'amount' => $this->amount, 'date' => $this->date];
    }

    private function path(): string
    {
        return '/services/invoices/'.$this->invoiceId;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'service_payment_due', 'title_key' => 'notifications.service_payment.'.$this->event, 'params' => $this->params(), 'url' => $this->path()];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : (string) config('app.locale');

        return (new MailMessage)
            ->subject(__("notifications.service_payment.{$this->event}.subject", $this->params(), $locale))
            ->greeting(__('mail.greeting', [], $locale))
            ->line(__("notifications.service_payment.{$this->event}.line", $this->params(), $locale))
            ->action(__('notifications.open_invoice', [], $locale), rtrim((string) config('app.url'), '/').$this->path())
            ->salutation(__('mail.salutation', [], $locale));
    }
}
