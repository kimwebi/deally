<?php

namespace SaasFoundation\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorRecoveryCodesNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected array $recoveryCodes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $codes = implode("\n", $this->recoveryCodes);

        return (new MailMessage)
            ->subject('Two-Factor Authentication Recovery Codes')
            ->greeting('Hello, '.$notifiable->name.'!')
            ->line('Here are your recovery codes for two-factor authentication. Store them somewhere safe.')
            ->line('<pre>'.$codes.'</pre>')
            ->line('Each code can only be used once. If you lose these codes, you will be unable to access your account without verifying your identity.')
            ->line('If you did not request new recovery codes, please secure your account immediately.');
    }
}
