<?php

namespace App\Notifications;

use App\Models\ReportExport;
use App\Models\User;
use App\Notifications\Channels\ChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A requested report export finished. */
class ExportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $type;

    public string $format;

    public function __construct(ReportExport $export)
    {
        $this->type = $export->type;
        $this->format = $export->format;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? app(ChannelResolver::class)->for($notifiable, 'export_ready') : ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'export_ready',
            'title_key' => 'notifications.export_ready',
            'params' => ['report' => $this->type, 'format' => $this->format],
            'url' => '/exports',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : (string) config('app.locale');
        $report = __("exports.{$this->type}.title", [], $locale);

        return (new MailMessage)
            ->subject(__('notifications.export_ready.subject', ['report' => $report], $locale))
            ->greeting(__('mail.greeting', [], $locale))
            ->line(__('notifications.export_ready.line', ['report' => $report], $locale))
            ->action(__('notifications.open_exports', [], $locale), rtrim((string) config('app.url'), '/').'/exports')
            ->salutation(__('mail.salutation', [], $locale));
    }
}
