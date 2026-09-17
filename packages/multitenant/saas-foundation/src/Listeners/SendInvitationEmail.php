<?php

namespace SaasFoundation\Listeners;

use Illuminate\Support\Facades\Notification;
use SaasFoundation\Events\InvitationCreated;
use SaasFoundation\Notifications\TenantInvitationNotification;

class SendInvitationEmail
{
    public function handle(InvitationCreated $event): void
    {
        Notification::route('mail', $event->invitation->email)
            ->notify(new TenantInvitationNotification(
                $event->tenant,
                $event->invitation,
            ));
    }
}
