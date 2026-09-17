<?php

namespace Database\Factories;

use Deally\Proposals\Models\Proposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proposal>
 */
class ProposalFactory extends Factory
{
    protected $model = Proposal::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'company' => fake()->company(),
            'value' => fake()->numberBetween(5000, 90000),
            'status' => fake()->randomElement(['viewed', 'approved', 'rejected', 'draft']),
            'package' => fake()->randomElement(['Enterprise Suite', 'Pro Plan', 'Starter']),
            'quote' => null,
            'line_items' => null,
        ];
    }
}
