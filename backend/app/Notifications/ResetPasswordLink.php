<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Password reset e-mail (Uzbek). The link points to the SPA's /reset-password page
 * (URL callback registered in AppServiceProvider).
 */
class ResetPasswordLink extends ResetPassword implements ShouldQueue
{
    use Queueable;

    protected function buildMailMessage($url): MailMessage
    {
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject(__('mail.reset_subject'))
            ->greeting(__('mail.greeting'))
            ->line(__('mail.reset_intro'))
            ->action(__('mail.reset_action'), $url)
            ->line(__('mail.reset_expire', ['count' => $minutes]))
            ->line(__('mail.reset_ignore'))
            ->salutation(__('mail.salutation'));
    }
}
