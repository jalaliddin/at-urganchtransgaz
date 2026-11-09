<?php

namespace App\Notifications\Itsm;

use App\Models\User;
use App\Notifications\Channels\ChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Problem events: you are responsible for it; a problem behind your ticket was resolved (design R6). */
class ProblemNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private const TYPES = ['assigned' => 'problem_assigned', 'resolved' => 'problem_resolved'];

    /** @param  'assigned'|'resolved'  $event */
    public function __construct(public string $event, public int $problemId, public string $number, public string $title, public string $actor = '')
    {
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
        return ['number' => $this->number, 'title' => $this->title, 'actor' => $this->actor];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['type' => self::TYPES[$this->event], 'title_key' => 'notifications.problem.'.$this->event, 'params' => $this->params(), 'url' => '/problems/'.$this->problemId];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : (string) config('app.locale');

        return (new MailMessage)
            ->subject(__("notifications.problem.{$this->event}.subject", $this->params(), $locale))
            ->greeting(__('mail.greeting', [], $locale))
            ->line(__("notifications.problem.{$this->event}.line", $this->params(), $locale))
            ->action(__('notifications.open_problem', [], $locale), rtrim((string) config('app.url'), '/').'/problems/'.$this->problemId)
            ->salutation(__('mail.salutation', [], $locale));
    }
}
