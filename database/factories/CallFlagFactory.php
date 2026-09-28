<?php

namespace Database\Factories;

use Deally\Calls\Models\Call;
use Deally\Calls\Models\CallFlag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallFlag>
 */
class CallFlagFactory extends Factory
{
    protected $model = CallFlag::class;

    public function definition(): array
    {
        return [
            'call_id' => Call::factory(),
            'kind' => CallFlag::KIND_DEAL_RISK,
            'status' => CallFlag::STATUS_OPEN,
            'headline' => fake()->sentence(5),
            'rationale' => fake()->sentence(12),
            'resolution_note' => null,
            'opportunity_stage_id' => null,
            'resolved_at' => null,
        ];
    }

    public function resolved(string $status = CallFlag::STATUS_CONFIRMED): static
    {
        return $this->state(fn (): array => [
            'status' => $status,
            'resolved_at' => now(),
        ]);
    }
}
