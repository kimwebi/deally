<?php

namespace Database\Factories;

use Deally\Calls\Models\Call;
use Deally\Calls\Models\CallEphemeral;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallEphemeral>
 */
class CallEphemeralFactory extends Factory
{
    protected $model = CallEphemeral::class;

    public function definition(): array
    {
        return [
            'call_id' => Call::factory(),
            'transcript_line_id' => null,
            'kind' => CallEphemeral::KIND_HEARD,
            'label' => 'Heard',
            'body' => fake()->words(fake()->numberBetween(5, 12), true),
            'source' => 'groq',
            'occurred_at_ms' => null,
        ];
    }

    public function kind(string $kind, string $label): static
    {
        return $this->state(fn (): array => ['kind' => $kind, 'label' => $label]);
    }
}
