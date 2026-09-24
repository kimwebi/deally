<?php

namespace Database\Factories;

use Deally\Pipeline\Models\ServiceReviewSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceReviewSchedule>
 */
class ServiceReviewScheduleFactory extends Factory
{
    protected $model = ServiceReviewSchedule::class;

    public function definition(): array
    {
        return [
            'customer_id' => 1,
            'cadence_days' => 30,
            'status' => ServiceReviewSchedule::STATUS_ACTIVE,
            'started_at' => now(),
            'ended_at' => null,
        ];
    }
}
