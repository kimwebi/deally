<?php

namespace SaasFoundation\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use SaasFoundation\Models\Plan;
use SaasFoundation\Models\Subscription;
use SaasFoundation\Models\Tenant;

class SubscriptionChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public Tenant $tenant,
        public ?Plan $oldPlan,
        public Plan $newPlan,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tenant.'.$this->tenant->id)];
    }

    public function broadcastAs(): string
    {
        return 'subscription.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'tenant_id' => $this->tenant->id,
            'old_plan_id' => $this->oldPlan?->id,
            'old_plan_slug' => $this->oldPlan?->slug,
            'new_plan_id' => $this->newPlan->id,
            'new_plan_slug' => $this->newPlan->slug,
        ];
    }
}
