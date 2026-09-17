<?php

namespace SaasFoundation\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use SaasFoundation\Models\Membership;

class MembershipReactivated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Membership $membership,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tenant.'.$this->membership->tenant_id)];
    }

    public function broadcastAs(): string
    {
        return 'membership.reactivated';
    }

    public function broadcastWith(): array
    {
        return [
            'membership_id' => $this->membership->id,
            'user_id' => $this->membership->user_id,
            'tenant_id' => $this->membership->tenant_id,
            'status' => $this->membership->status,
        ];
    }
}
