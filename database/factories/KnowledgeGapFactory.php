<?php

namespace Database\Factories;

use Deally\Proposals\Models\KnowledgeGap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeGap>
 */
class KnowledgeGapFactory extends Factory
{
    protected $model = KnowledgeGap::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['gap', 'correction', 'competitor']),
            'text' => fake()->sentence(8),
            'source' => fake()->company().' · '.fake()->date('M d'),
            'status' => 'pending',
        ];
    }
}
