<?php

namespace Deally\Core\Services;

use Illuminate\Database\Eloquent\Model;
use SaasFoundation\Models\Activity;

class ActivityLogger
{
    public function log(string $event, string $description, array $properties = [], string $level = 'info', ?Model $subject = null): Activity
    {
        $user = auth()->user();
        $membership = $user?->currentMembership ?? $user?->memberships()->active()->with('tenant')->first();

        return Activity::query()->create([
            'tenant_id' => $membership?->tenant_id,
            'user_id' => $user?->getKey(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'event' => $event,
            'description' => $description,
            'properties' => array_merge($properties, ['level' => $level]),
        ]);
    }
}
