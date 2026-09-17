<?php

namespace Database\Factories;

use Deally\Pipeline\Models\Opportunity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    protected $model = Opportunity::class;

    public function definition(): array
    {
        return [
            'company' => fake()->company(),
            'contact_name' => fake()->name(),
            'contact_title' => fake()->jobTitle(),
            'packages' => 'Pro Plan (1)',
            'stage' => fake()->randomElement(Opportunity::stages()),
            'value' => fake()->numberBetween(5000, 80000),
        ];
    }
}
