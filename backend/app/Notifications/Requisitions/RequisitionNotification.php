<?php

namespace App\Notifications\Requisitions;

use App\Models\User;
use App\Notifications\Channels\ChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A requisition step that concerns the recipient. $event is one of EVENTS; events in
 * ACTIONS ask the recipient to act (channel type requisition_action), the rest
 * inform (requisition_update).
 */
class RequisitionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const EVENTS = ['org_approval', 'queued', 'info_requested', 'info_provided', 'approved', 'allocation', 'rejected', 'returned', 'partially_fulfilled', 'fulfilled', 'received'];

    private const ACTIONS = ['org_approval', 'queued', 'info_requested', 'info_provided', 'allocation', 'returned', 'fulfilled'];

    public function __construct(
        public string $event, public int $requisitionId, public string $number, public string $title,
        public string $organization, public ?string $comment = null,
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $type = in_array($this->event, self::ACTIONS, true) ? 'requisition_action' : 'requisition_update';

        return $notifiable instanceof User ? app(ChannelResolver::class)->for($notifiable, $type) : ['database'];
    }

    /** @return array<string, string> */
    private function params(): array
    {
        return ['number' => $this->number, 'title' => $this->title, 'organization' => $this->organization];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'requisition_'.$this->event,
            'title_key' => 'notifications.requisition_'.$this->event,
            'params' => [...$this->params(), 'comment' => $this->comment],
            'url' => "/requisitions/{$this->requisitionId}",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : (string) config('app.locale');
        $mail = (new MailMessage)
            ->subject(__("notifications.requisition.{$this->event}.subject", $this->params(), $locale))
            ->greeting(__('mail.greeting', [], $locale))
            ->line(__("notifications.requisition.{$this->event}.line", $this->params(), $locale));
        if ($this->comment !== null && $this->comment !== '') {
            $mail->line('«'.$this->comment.'»');
        }

        return $mail->action(__('notifications.open_requisition', [], $locale), rtrim((string) config('app.url'), '/')."/requisitions/{$this->requisitionId}")
            ->salutation(__('mail.salutation', [], $locale));
    }
}
