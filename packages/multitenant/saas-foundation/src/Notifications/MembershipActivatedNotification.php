<?php

namespace SaasFoundation\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Tenant;

class MembershipActivatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Membership $membership,
        protected Tenant $tenant,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You are now a member of '.$this->tenant->name)
            ->greeting('Hello, '.$notifiable->name.'!')
            ->line('Your membership in the "'.$this->tenant->name.'" workspace has been activated.')
            ->action('Go to Workspace', route('dashboard'))
            ->line('Welcome aboard!');
    }
}
