<?php

namespace SaasFoundation\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use SaasFoundation\Models\Subscription;
use SaasFoundation\Models\Tenant;

class SubscriptionCancelled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public Tenant $tenant,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tenant.'.$this->tenant->id)];
    }

    public function broadcastAs(): string
    {
        return 'subscription.cancelled';
    }

    public function broadcastWith(): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'plan_id' => $this->subscription->plan_id,
            'tenant_id' => $this->tenant->id,
            'cancelled_at' => $this->subscription->cancelled_at?->toISOString(),
        ];
    }
}
