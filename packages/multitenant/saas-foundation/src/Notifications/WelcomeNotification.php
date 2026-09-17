<?php

namespace SaasFoundation\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use SaasFoundation\Models\Tenant;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Tenant $tenant,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to '.$this->tenant->name)
            ->greeting('Welcome, '.$notifiable->name.'!')
            ->line('Your workspace "'.$this->tenant->name.'" has been created successfully.')
            ->action('Go to Dashboard', route('dashboard'))
            ->line('We are excited to have you on board.');
    }
}
