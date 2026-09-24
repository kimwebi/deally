<?php

namespace Database\Factories;

use Deally\Pipeline\Models\ServiceReviewSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceReviewSession>
 */
class ServiceReviewSessionFactory extends Factory
{
    protected $model = ServiceReviewSession::class;

    public function definition(): array
    {
        return [
            'schedule_id' => 1,
            'scheduled_at' => now()->addDays(30),
            'status' => ServiceReviewSession::STATUS_SCHEDULED,
            'notes' => null,
        ];
    }
}
