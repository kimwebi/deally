<?php

namespace SaasFoundation\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;
use SaasFoundation\Services\Tenancy\TenantContext;

class SendTenantNotification implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        protected Tenant $tenant,
        protected User $user,
        protected Notification $notification,
    ) {}

    public function handle(TenantContext $context): void
    {
        $context->initialize($this->tenant);

        try {
            $this->user->notify($this->notification);
        } finally {
            $context->end();
        }
    }

    public function middleware(): array
    {
        return [new WithoutOverlapping('tenant_'.$this->tenant->id)];
    }
}
