<?php

namespace SaasFoundation\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use SaasFoundation\Models\Tenant;

class TenantDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
    ) {}
}
