<?php

namespace App\Notifications\Tasks;

use App\Models\User;
use App\Notifications\Channels\ChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Task events for its responsible person and watchers: assigned, commented, due soon, overdue. */
class TaskNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private const TYPES = ['assigned' => 'task_assigned', 'commented' => 'task_commented', 'due_soon' => 'task_due', 'overdue' => 'task_due'];

    /** @param  'assigned'|'commented'|'due_soon'|'overdue'  $event */
    public function __construct(
        public string $event,
        public int $taskId,
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
        return ['type' => self::TYPES[$this->event], 'title_key' => 'notifications.task.'.$this->event, 'params' => $this->params(), 'url' => '/tasks/'.$this->taskId];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : (string) config('app.locale');

        return (new MailMessage)
            ->subject(__("notifications.task.{$this->event}.subject", $this->params(), $locale))
            ->greeting(__('mail.greeting', [], $locale))
            ->line(__("notifications.task.{$this->event}.line", $this->params(), $locale))
            ->action(__('notifications.open_task', [], $locale), rtrim((string) config('app.url'), '/').'/tasks/'.$this->taskId)
            ->salutation(__('mail.salutation', [], $locale));
    }
}
