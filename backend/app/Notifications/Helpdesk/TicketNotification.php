<?php

namespace App\Notifications\Helpdesk;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\Channels\ChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for ticket notifications. Stores plain values (not the model) so a queued
 * notification renders even if the ticket changes before delivery.
 */
abstract class TicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $ticketId;

    public string $number;

    public string $ticketTitle;

    public function __construct(Ticket $ticket)
    {
        $this->ticketId = $ticket->id;
        $this->number = $ticket->number;
        $this->ticketTitle = $ticket->title;
    }

    abstract public function type(): string;

    /** @return array<string, scalar|null> extra parameters for the message */
    protected function params(): array
    {
        return [];
    }

    /** @return array<string, scalar|null> parameters with translated labels for mail */
    protected function mailParams(string $locale): array
    {
        return $this->allParams();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? app(ChannelResolver::class)->for($notifiable, $this->type()) : ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type(),
            'title_key' => 'notifications.'.$this->type(),
            'params' => $this->allParams(),
            'url' => $this->path(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : (string) config('app.locale');
        $params = $this->mailParams($locale);

        return (new MailMessage)
            ->subject(__("notifications.{$this->type()}.subject", $params, $locale))
            ->greeting(__('mail.greeting', [], $locale))
            ->line(__("notifications.{$this->type()}.line", $params, $locale))
            ->line($this->number.': '.$this->ticketTitle)
            ->action(__('notifications.open_ticket', [], $locale), rtrim((string) config('app.url'), '/').$this->path())
            ->salutation(__('mail.salutation', [], $locale));
    }

    protected function path(): string
    {
        return '/helpdesk/'.$this->ticketId;
    }

    /** @return array<string, scalar|null> */
    private function allParams(): array
    {
        return ['number' => $this->number, 'title' => $this->ticketTitle, ...$this->params()];
    }
}
