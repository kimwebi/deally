<?php

namespace SaasFoundation\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;

class TenantSwitched
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Tenant $from,
        public Tenant $to,
        public User $user,
    ) {}
}
