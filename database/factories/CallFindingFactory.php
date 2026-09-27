<?php

namespace Database\Factories;

use Deally\Calls\Models\Call;
use Deally\Calls\Models\CallFinding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallFinding>
 */
class CallFindingFactory extends Factory
{
    protected $model = CallFinding::class;

    public function definition(): array
    {
        return [
            'call_id' => Call::factory(),
            'transcript_line_id' => null,
            'type' => CallFinding::TYPE_RECOMMENDATION,
            'kind' => 'say',
            'label' => 'Say this',
            'body' => fake()->sentence(10),
            'package' => fake()->words(3, true),
            'source' => 'Knowledge base · '.fake()->word(),
            'confidence' => fake()->randomFloat(2, 0.5, 0.99),
            'provider' => 'groq',
            'status' => CallFinding::STATUS_NEW,
            'dedupe_key' => fake()->unique()->sha1(),
        ];
    }

    public function signal(string $kind = 'objection'): static
    {
        return $this->state(fn (): array => [
            'type' => CallFinding::TYPE_SIGNAL,
            'kind' => $kind,
            'label' => ucfirst(str_replace('_', ' ', $kind)),
        ]);
    }

    public function unhelpful(): static
    {
        return $this->state(fn (): array => ['status' => CallFinding::STATUS_UNHELPFUL]);
    }
}
