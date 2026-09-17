<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SaasFoundation\Models\Activity;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'subject_type' => null,
            'subject_id' => null,
            'event' => fake()->randomElement(['created', 'updated', 'deleted', 'login', 'logout']),
            'description' => fake()->sentence(),
            'properties' => null,
        ];
    }
}
