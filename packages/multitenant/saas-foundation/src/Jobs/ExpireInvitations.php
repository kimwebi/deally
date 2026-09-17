<?php

namespace SaasFoundation\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SaasFoundation\Models\Invitation;

class ExpireInvitations implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Invitation::where('status', Invitation::STATUS_PENDING)
            ->where('expires_at', '<', now())
            ->update(['status' => Invitation::STATUS_EXPIRED]);
    }
}
