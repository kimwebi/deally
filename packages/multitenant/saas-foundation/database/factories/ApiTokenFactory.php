<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use SaasFoundation\Models\ApiToken;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;

/**
 * @extends Factory<ApiToken>
 */
class ApiTokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'name' => fake()->word(),
            'token' => Str::random(64),
            'abilities' => ['*'],
            'last_used_at' => null,
            'expires_at' => now()->addYear(),
            'metadata' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }
}
