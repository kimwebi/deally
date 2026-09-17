<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SaasFoundation\Models\Project;
use SaasFoundation\Models\Tenant;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'status' => 'active',
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => 'archived']);
    }
}
