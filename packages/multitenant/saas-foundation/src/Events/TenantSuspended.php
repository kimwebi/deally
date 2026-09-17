<?php

namespace SaasFoundation\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use SaasFoundation\Models\Tenant;

class TenantSuspended implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public ?string $reason = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tenant.'.$this->tenant->id)];
    }

    public function broadcastAs(): string
    {
        return 'tenant.suspended';
    }

    public function broadcastWith(): array
    {
        return [
            'tenant_id' => $this->tenant->id,
            'name' => $this->tenant->name,
            'reason' => $this->reason,
        ];
    }
}
