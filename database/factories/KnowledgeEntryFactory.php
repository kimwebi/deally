<?php

namespace Database\Factories;

use Deally\Proposals\Models\KnowledgeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeEntry>
 */
class KnowledgeEntryFactory extends Factory
{
    protected $model = KnowledgeEntry::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(KnowledgeEntry::types()),
            'title' => fake()->words(3, true),
            'description' => fake()->sentence(8),
        ];
    }
}
