<?php

namespace Database\Seeders;

use Deally\Core\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User'],
        );

        $user->forceFill([
            'is_super_admin' => true,
            'is_superadmin' => true,
            'is_admin' => true,
        ])->save();
    }
}
