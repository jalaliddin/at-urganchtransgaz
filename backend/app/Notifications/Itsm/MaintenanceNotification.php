<?php

namespace App\Notifications\Itsm;

use App\Models\User;
use App\Notifications\Channels\ChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Preventive maintenance reminders (design M2): due within the lead days, overdue. */
class MaintenanceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  'due_soon'|'overdue'  $event */
    public function __construct(public string $event, public int $taskId, public string $title, public string $asset, public string $date)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? app(ChannelResolver::class)->for($notifiable, 'maintenance_due') : ['database'];
    }

    /** @return array<string, string> */
    private function params(): array
    {
        return ['title' => $this->title, 'asset' => $this->asset, 'date' => $this->date];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'maintenance_due', 'title_key' => 'notifications.maintenance.'.$this->event, 'params' => $this->params(),
            'url' => '/maintenance?task='.$this->taskId];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : (string) config('app.locale');

        return (new MailMessage)
            ->subject(__("notifications.maintenance.{$this->event}.subject", $this->params(), $locale))
            ->greeting(__('mail.greeting', [], $locale))
            ->line(__("notifications.maintenance.{$this->event}.line", $this->params(), $locale))
            ->action(__('notifications.open_maintenance', [], $locale), rtrim((string) config('app.url'), '/').'/maintenance')
            ->salutation(__('mail.salutation', [], $locale));
    }
}
