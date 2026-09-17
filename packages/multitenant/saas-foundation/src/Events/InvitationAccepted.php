<?php

namespace SaasFoundation\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use SaasFoundation\Models\Invitation;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;

class InvitationAccepted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Invitation $invitation,
        public Tenant $tenant,
        public User $user,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tenant.'.$this->tenant->id)];
    }

    public function broadcastAs(): string
    {
        return 'invitation.accepted';
    }

    public function broadcastWith(): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'email' => $this->invitation->email,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ];
    }
}
