<?php

namespace Database\Factories;

use Deally\Calls\Models\Call;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Call>
 */
class CallFactory extends Factory
{
    protected $model = Call::class;

    public function definition(): array
    {
        return [
            'opportunity_id' => null,
            'name' => fake()->randomElement(['Demo & Discovery', 'Discovery', 'Intro Call']),
            'company' => fake()->company(),
            'date' => fake()->date(),
            'duration' => fake()->randomElement(['12m', '18m', '24m', '30m']),
            'sentiment' => fake()->randomElement(['positive', 'neutral', 'negative']),
            'contact_name' => fake()->name(),
            'contact_role' => fake()->jobTitle(),
            'notes' => null,
            'summary' => null,
        ];
    }
}
