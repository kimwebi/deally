<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
            'invited_at' => null,
            'accepted_at' => now(),
            'metadata' => null,
        ];
    }

    public function invited(): static
    {
        return $this->state(fn () => [
            'invited_at' => now()->subDay(),
            'accepted_at' => null,
            'status' => Membership::STATUS_INACTIVE,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => Membership::STATUS_SUSPENDED]);
    }
}
