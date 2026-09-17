<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use SaasFoundation\Models\Plan;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 9, 299),
            'currency' => 'USD',
            'billing_interval' => 'monthly',
            'trial_days' => 14,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 0,
            'metadata' => null,
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => [
            'price' => 0,
            'is_default' => true,
        ]);
    }

    public function annual(): static
    {
        return $this->state(fn () => ['billing_interval' => 'annual']);
    }
}
