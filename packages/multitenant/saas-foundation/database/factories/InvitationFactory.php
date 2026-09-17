<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use SaasFoundation\Models\Invitation;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role_id' => Role::factory(),
            'invited_by' => User::factory(),
            'token' => Str::random(64),
            'status' => Invitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'metadata' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => Invitation::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => Invitation::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
        ]);
    }
}
