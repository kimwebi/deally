<?php

namespace SaasFoundation\Listeners;

use SaasFoundation\Events\SubscriptionChanged;
use SaasFoundation\Notifications\SubscriptionChangedNotification;

class NotifySubscriptionChanged
{
    public function handle(SubscriptionChanged $event): void
    {
        $tenant = $event->tenant;

        $owner = $tenant->memberships()
            ->where('status', 'active')
            ->with('user')
            ->first()?->user;

        if ($owner !== null) {
            $owner->notify(new SubscriptionChangedNotification(
                $event->subscription,
                $event->oldPlan,
                $event->newPlan,
            ));
        }
    }
}
