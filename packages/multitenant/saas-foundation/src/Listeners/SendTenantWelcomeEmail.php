<?php

namespace SaasFoundation\Listeners;

use SaasFoundation\Events\TenantCreated;
use SaasFoundation\Notifications\WelcomeNotification;

class SendTenantWelcomeEmail
{
    public function handle(TenantCreated $event): void
    {
        $tenant = $event->tenant;

        $owner = $tenant->memberships()
            ->where('status', 'active')
            ->with('user')
            ->first()?->user;

        if ($owner !== null) {
            $owner->notify(new WelcomeNotification($tenant));
        }
    }
}
