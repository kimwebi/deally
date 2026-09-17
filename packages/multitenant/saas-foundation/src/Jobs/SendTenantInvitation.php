<?php

namespace SaasFoundation\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use SaasFoundation\Models\Invitation;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Notifications\TenantInvitationNotification;
use SaasFoundation\Services\Tenancy\TenantContext;

class SendTenantInvitation implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        protected Invitation $invitation,
        protected Tenant $tenant,
    ) {}

    public function handle(TenantContext $context): void
    {
        $context->initialize($this->tenant);

        try {
            Notification::route('mail', $this->invitation->email)
                ->notify(new TenantInvitationNotification($this->tenant, $this->invitation));
        } finally {
            $context->end();
        }
    }

    public function middleware(): array
    {
        return [new WithoutOverlapping('tenant_'.$this->tenant->id)];
    }
}
