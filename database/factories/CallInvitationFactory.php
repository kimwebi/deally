<?php

namespace Database\Factories;

use Deally\Calls\Models\Call;
use Deally\Calls\Models\CallInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallInvitation>
 */
class CallInvitationFactory extends Factory
{
    protected $model = CallInvitation::class;

    public function definition(): array
    {
        return [
            'call_id' => Call::factory(),
            'channel' => 'email',
            'recipient_name' => fake()->name(),
            'recipient_email' => fake()->safeEmail(),
            'subject' => 'DeAlly call with '.fake()->name(),
            'body' => fake()->paragraph(),
            'status' => CallInvitation::STATUS_PENDING,
            'delivery_error' => null,
            'sent_at' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => CallInvitation::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    public function failed(string $error = 'Connection could not be established.'): static
    {
        return $this->state(fn (): array => [
            'status' => CallInvitation::STATUS_FAILED,
            'delivery_error' => $error,
        ]);
    }
}
