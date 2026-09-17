<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'is_system' => false,
        ];
    }

    public function administrator(): static
    {
        return $this->state(fn () => [
            'name' => 'Administrator',
            'slug' => 'administrator',
            'is_system' => true,
        ]);
    }

    public function system(): static
    {
        return $this->state(fn () => ['is_system' => true]);
    }
}
