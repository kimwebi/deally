<?php

namespace SaasFoundation\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Tenancy\TenantContext;
use SaasFoundation\Services\Tenancy\TenantProvisioner;

class RunTenantMigrations implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        protected Tenant $tenant,
    ) {}

    public function handle(TenantContext $context, TenantProvisioner $provisioner): void
    {
        $context->initialize($this->tenant);

        try {
            $provisioner->migrate($this->tenant);
        } finally {
            $context->end();
        }
    }

    public function middleware(): array
    {
        return [new WithoutOverlapping('tenant_'.$this->tenant->id)];
    }
}
