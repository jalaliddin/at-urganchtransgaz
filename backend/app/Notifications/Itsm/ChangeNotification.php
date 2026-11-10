<?php

namespace App\Notifications\Itsm;

use App\Models\User;
use App\Notifications\Channels\ChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Change events (design C6): waiting for approval / emergency (approvers), decided (requester, implementer), assigned. */
class ChangeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private const TYPES = ['approval' => 'change_approval', 'emergency' => 'change_approval', 'approved' => 'change_update',
        'rejected' => 'change_update', 'assigned' => 'change_update'];

    /** @param  'approval'|'emergency'|'approved'|'rejected'|'assigned'  $event */
    public function __construct(
        public string $event,
        public int $changeId,
        public string $number,
        public string $title,
        public string $actor = '',
        public string $date = '',
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? app(ChannelResolver::class)->for($notifiable, self::TYPES[$this->event]) : ['database'];
    }

    /** @return array<string, string> */
    private function params(): array
    {
        return ['number' => $this->number, 'title' => $this->title, 'actor' => $this->actor, 'date' => $this->date];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['type' => self::TYPES[$this->event], 'title_key' => 'notifications.change.'.$this->event, 'params' => $this->params(), 'url' => '/changes/'.$this->changeId];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : (string) config('app.locale');

        return (new MailMessage)
            ->subject(__("notifications.change.{$this->event}.subject", $this->params(), $locale))
            ->greeting(__('mail.greeting', [], $locale))
            ->line(__("notifications.change.{$this->event}.line", $this->params(), $locale))
            ->action(__('notifications.open_change', [], $locale), rtrim((string) config('app.url'), '/').'/changes/'.$this->changeId)
            ->salutation(__('mail.salutation', [], $locale));
    }
}
