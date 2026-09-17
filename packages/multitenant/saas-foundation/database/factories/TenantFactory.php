<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use SaasFoundation\Models\Tenant;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'status' => Tenant::STATUS_ACTIVE,
            'timezone' => fake()->timezone(),
            'locale' => 'en',
            'currency' => 'USD',
            'metadata' => null,
            'settings' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => Tenant::STATUS_PENDING]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => Tenant::STATUS_SUSPENDED]);
    }

    public function trial(): static
    {
        return $this->state(fn () => ['status' => Tenant::STATUS_TRIAL]);
    }
}
