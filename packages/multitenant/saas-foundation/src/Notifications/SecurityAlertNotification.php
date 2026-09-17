<?php

namespace SaasFoundation\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SecurityAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $alert,
        protected array $context = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Security Alert')
            ->greeting('Hello, '.$notifiable->name.'!')
            ->line('We detected the following security event on your account:')
            ->line('**'.$this->alert.'**')
            ->line('If this was you, you can ignore this alert.')
            ->line('If you do not recognize this activity, please secure your account immediately.')
            ->action('Review Account Security', route('security'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'alert' => $this->alert,
            'context' => $this->context,
        ];
    }
}
