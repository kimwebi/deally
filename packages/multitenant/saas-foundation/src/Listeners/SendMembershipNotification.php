<?php

namespace SaasFoundation\Listeners;

use SaasFoundation\Events\MembershipCreated;
use SaasFoundation\Notifications\MembershipActivatedNotification;

class SendMembershipNotification
{
    public function handle(MembershipCreated $event): void
    {
        $user = $event->membership->user;

        if ($user !== null) {
            $user->notify(new MembershipActivatedNotification(
                $event->membership,
                $event->tenant,
            ));
        }
    }
}
