<?php

namespace SaasFoundation\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use SaasFoundation\Models\Tenant;

class TenantSuspendedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Tenant $tenant,
        protected ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Your workspace has been suspended')
            ->greeting('Hello, '.$notifiable->name.'!')
            ->line('Your workspace "'.$this->tenant->name.'" has been suspended.');

        if ($this->reason !== null) {
            $message->line('Reason: '.$this->reason);
        }

        return $message
            ->line('If you believe this is a mistake, please contact support.')
            ->action('Contact Support', route('support'));
    }
}
