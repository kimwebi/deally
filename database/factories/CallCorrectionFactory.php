<?php

namespace Database\Factories;

use Deally\Calls\Models\Call;
use Deally\Calls\Models\CallCorrection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallCorrection>
 */
class CallCorrectionFactory extends Factory
{
    protected $model = CallCorrection::class;

    public function definition(): array
    {
        return [
            'call_id' => Call::factory(),
            'field' => CallCorrection::FIELD_SENTIMENT,
            'target_type' => null,
            'target_id' => null,
            'ai_value' => 'neutral',
            'corrected_value' => 'positive',
            'note' => null,
            'corrected_by_user_id' => null,
        ];
    }

    public function field(string $field, string $aiValue, string $correctedValue): static
    {
        return $this->state(fn (): array => [
            'field' => $field,
            'ai_value' => $aiValue,
            'corrected_value' => $correctedValue,
        ]);
    }
}
