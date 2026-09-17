<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SaasFoundation\Models\Plan;
use SaasFoundation\Models\Subscription;
use SaasFoundation\Models\Tenant;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => Plan::factory(),
            'status' => Subscription::STATUS_ACTIVE,
            'trial_ends_at' => null,
            'starts_at' => now(),
            'ends_at' => null,
            'cancelled_at' => null,
            'billing_cycle_anchor' => now(),
            'metadata' => null,
        ];
    }

    public function trialing(): static
    {
        return $this->state(fn () => [
            'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => Subscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }
}
