<?php

namespace SaasFoundation\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use SaasFoundation\Events\TenantDeleted;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Tenancy\TenantContext;

class DeleteTenantData implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        protected Tenant $tenant,
        protected bool $forceDelete = false,
    ) {}

    public function handle(TenantContext $context): void
    {
        $context->initialize($this->tenant);

        try {
            $tenant = $this->tenant;

            $tenant->domains()->delete();
            $tenant->memberships()->delete();
            $tenant->roles()->delete();
            $tenant->settings()->delete();
            $tenant->subscriptions()->delete();

            event(new TenantDeleted($tenant));

            if ($this->forceDelete) {
                $tenant->forceDelete();
            } else {
                $tenant->delete();
            }
        } finally {
            $context->end();
        }
    }

    public function middleware(): array
    {
        return [new WithoutOverlapping('tenant_'.$this->tenant->id)];
    }
}
