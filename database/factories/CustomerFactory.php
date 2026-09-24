<?php

namespace Database\Factories;

use Deally\Pipeline\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'company' => fake()->unique()->company(),
            'contact_name' => fake()->name(),
            'contact_title' => fake()->jobTitle(),
            'owner_user_id' => 1,
            'team_id' => null,
        ];
    }
}
