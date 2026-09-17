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

class ProcessTenantProvisioning implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        protected Tenant $tenant,
        protected array $data = [],
    ) {}

    public function handle(TenantContext $context, TenantProvisioner $provisioner): void
    {
        $context->initialize($this->tenant);

        try {
            $provisioner->provision($this->data);

            $this->tenant->update([
                'status' => Tenant::STATUS_ACTIVE,
                'provisioning_status' => 'completed',
            ]);
        } finally {
            $context->end();
        }
    }

    public function middleware(): array
    {
        return [new WithoutOverlapping('tenant_'.$this->tenant->id)];
    }

    public function failed(\Throwable $e): void
    {
        $this->tenant->update(['provisioning_status' => 'failed']);
    }
}
