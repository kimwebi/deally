<?php

namespace Database\Factories;

use Deally\Calls\Models\Call;
use Deally\Calls\Models\TranscriptLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TranscriptLine>
 */
class TranscriptLineFactory extends Factory
{
    protected $model = TranscriptLine::class;

    public function definition(): array
    {
        return [
            'call_id' => Call::factory(),
            'speaker' => fake()->randomElement(['Customer', 'Agent']),
            'is_agent' => false,
            'text' => fake()->sentence(12),
            'sequence' => 0,
            'linked_type' => null,
            'linked_text' => null,
        ];
    }
}
