<?php

namespace App\Notifications\Assets;

use App\Models\Employee;
use App\Models\User;
use App\Notifications\Channels\ChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A dismissed employee still holds assets that must be collected. */
class AssetsReturnRequiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $employeeId;

    public string $employeeName;

    /** @param  list<string>  $inventoryNumbers */
    public function __construct(Employee $employee, public array $inventoryNumbers)
    {
        $this->employeeId = $employee->id;
        $this->employeeName = $employee->full_name;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? app(ChannelResolver::class)->for($notifiable, 'assets_return_required') : ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'assets_return_required',
            'title_key' => 'notifications.assets_return_required',
            'params' => ['employee' => $this->employeeName, 'count' => count($this->inventoryNumbers), 'assets' => implode(', ', $this->inventoryNumbers)],
            'url' => "/employees/{$this->employeeId}",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : (string) config('app.locale');
        $params = ['employee' => $this->employeeName, 'count' => count($this->inventoryNumbers)];

        return (new MailMessage)
            ->subject(__('notifications.assets_return_required.subject', $params, $locale))
            ->greeting(__('mail.greeting', [], $locale))
            ->line(__('notifications.assets_return_required.line', $params, $locale))
            ->line(implode(', ', $this->inventoryNumbers))
            ->action(__('notifications.open_employee', [], $locale), rtrim((string) config('app.url'), '/')."/employees/{$this->employeeId}")
            ->salutation(__('mail.salutation', [], $locale));
    }
}
