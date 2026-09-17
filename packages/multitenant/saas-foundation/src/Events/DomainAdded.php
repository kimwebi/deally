<?php

namespace SaasFoundation\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use SaasFoundation\Models\Domain;
use SaasFoundation\Models\Tenant;

class DomainAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Domain $domain,
        public Tenant $tenant,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tenant.'.$this->tenant->id)];
    }

    public function broadcastAs(): string
    {
        return 'domain.added';
    }

    public function broadcastWith(): array
    {
        return [
            'domain_id' => $this->domain->id,
            'domain' => $this->domain->domain,
            'type' => $this->domain->type,
            'tenant_id' => $this->tenant->id,
        ];
    }
}
