<?php

namespace App\Notifications\Software;

use App\Models\User;
use App\Notifications\Channels\ChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A software licence ends soon (once per licence and expiry date). */
class LicenseExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $licenseId, public string $software, public string $number, public string $organization, public string $date) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? app(ChannelResolver::class)->for($notifiable, 'license_expiring') : ['database'];
    }

    /** @return array<string, string> */
    private function params(): array
    {
        return ['software' => $this->software, 'number' => $this->number, 'organization' => $this->organization, 'date' => $this->date];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'license_expiring', 'title_key' => 'notifications.license_expiring', 'params' => $this->params(), 'url' => '/software/licenses?filter[expiring]=1'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : (string) config('app.locale');

        return (new MailMessage)
            ->subject(__('notifications.license_expiring.subject', $this->params(), $locale))
            ->greeting(__('mail.greeting', [], $locale))
            ->line(__('notifications.license_expiring.line', $this->params(), $locale))
            ->action(__('notifications.open_licenses', [], $locale), rtrim((string) config('app.url'), '/').'/software/licenses?filter[expiring]=1')
            ->salutation(__('mail.salutation', [], $locale));
    }
}
