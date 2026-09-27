<?php

namespace Database\Factories;

use Deally\Calls\Models\Call;
use Deally\Calls\Models\CallRecording;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CallRecording>
 */
class CallRecordingFactory extends Factory
{
    protected $model = CallRecording::class;

    public function definition(): array
    {
        $sequence = $this->faker->numberBetween(0, 500);

        return [
            'call_id' => Call::factory(),
            'source' => $this->faker->randomElement([CallRecording::SOURCE_AGENT, CallRecording::SOURCE_CUSTOMER]),
            'client_chunk_id' => Str::random(24),
            'client_sequence' => $sequence,
            'path' => 'calls/'.Str::random(8).'/customer-'.$sequence.'.webm',
            'mime' => 'audio/webm',
            'started_at_ms' => $sequence * 4000,
            'duration_ms' => 4000,
            'bytes' => $this->faker->numberBetween(8000, 90000),
        ];
    }

    public function agent(): static
    {
        return $this->state(fn (): array => ['source' => CallRecording::SOURCE_AGENT]);
    }

    public function customer(): static
    {
        return $this->state(fn (): array => ['source' => CallRecording::SOURCE_CUSTOMER]);
    }
}
