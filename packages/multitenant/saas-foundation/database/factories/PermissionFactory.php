<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use SaasFoundation\Models\Permission;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'group_name' => fake()->word(),
            'description' => fake()->sentence(),
        ];
    }

    public function view(): static
    {
        return $this->state(fn () => [
            'name' => 'Permission View',
            'slug' => 'permission.view',
            'group' => 'permissions',
        ]);
    }
}
