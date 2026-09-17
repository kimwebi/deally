<?php

namespace Database\Factories;

use Deally\Tasks\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'linked_company' => fake()->company(),
            'due_at' => fake()->dateTimeBetween('-5 days', '+10 days')->format('Y-m-d'),
            'status' => fake()->randomElement(['todo', 'closed']),
        ];
    }
}
