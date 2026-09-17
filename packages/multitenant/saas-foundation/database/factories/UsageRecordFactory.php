<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SaasFoundation\Models\Feature;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\UsageRecord;

/**
 * @extends Factory<UsageRecord>
 */
class UsageRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'feature_id' => Feature::factory(),
            'usage' => fake()->randomNumber(4),
            'period' => now()->format('Y-m'),
            'metadata' => null,
        ];
    }
}
