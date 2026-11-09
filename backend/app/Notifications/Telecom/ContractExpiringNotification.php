<?php

namespace App\Notifications\Telecom;

use App\Models\User;
use App\Notifications\Channels\ChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A provider contract ends soon (once per contract and end date). */
class ContractExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $contractId, public string $number, public string $provider, public string $organization, public string $date) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? app(ChannelResolver::class)->for($notifiable, 'contract_expiring') : ['database'];
    }

    /** @return array<string, string> */
    private function params(): array
    {
        return ['number' => $this->number, 'provider' => $this->provider, 'organization' => $this->organization, 'date' => $this->date];
    }

    private function path(): string
    {
        return '/services/contracts/'.$this->contractId;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'contract_expiring', 'title_key' => 'notifications.contract_expiring', 'params' => $this->params(), 'url' => $this->path()];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : (string) config('app.locale');

        return (new MailMessage)
            ->subject(__('notifications.contract_expiring.subject', $this->params(), $locale))
            ->greeting(__('mail.greeting', [], $locale))
            ->line(__('notifications.contract_expiring.line', $this->params(), $locale))
            ->action(__('notifications.open_contract', [], $locale), rtrim((string) config('app.url'), '/').$this->path())
            ->salutation(__('mail.salutation', [], $locale));
    }
}
