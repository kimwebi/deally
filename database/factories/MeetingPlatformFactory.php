<?php

namespace Database\Factories;

use Deally\Calls\Models\MeetingPlatform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingPlatform>
 */
class MeetingPlatformFactory extends Factory
{
    protected $model = MeetingPlatform::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->company(),
            'icon' => '🎥',
            'enabled' => true,
            'connected' => false,
            'connection_note' => 'Not connected.',
            'sort_order' => 10,
        ];
    }

    public function connected(): static
    {
        return $this->state(fn (): array => [
            'connected' => true,
            'connection_note' => null,
        ]);
    }
}
