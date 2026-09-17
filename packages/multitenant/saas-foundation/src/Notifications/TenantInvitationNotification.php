<?php

namespace SaasFoundation\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use SaasFoundation\Models\Invitation;
use SaasFoundation\Models\Tenant;

class TenantInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Tenant $tenant,
        protected ?Invitation $invitation = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->invitation
            ? route('invitations.accept', ['token' => $this->invitation->token])
            : route('register');

        return (new MailMessage)
            ->subject('Invitation to join '.$this->tenant->name)
            ->greeting('Hello!')
            ->line('You have been invited to join the "'.$this->tenant->name.'" workspace.')
            ->action('Accept Invitation', $url)
            ->line('If you did not expect this invitation, you can ignore this email.');
    }
}
