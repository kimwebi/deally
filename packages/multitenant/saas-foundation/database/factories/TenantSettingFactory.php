<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\TenantSetting;

/**
 * @extends Factory<TenantSetting>
 */
class TenantSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'key' => fake()->unique()->slug(2),
            'value' => fake()->word(),
            'type' => 'string',
        ];
    }

    public function boolean(): static
    {
        return $this->state(fn () => [
            'value' => fake()->boolean(),
            'type' => 'boolean',
        ]);
    }

    public function json(): static
    {
        return $this->state(fn () => [
            'value' => fake()->words(3),
            'type' => 'json',
        ]);
    }
}
