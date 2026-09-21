<?php

namespace Database\Seeders;

use Deally\Core\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call(DeallyAccessSeeder::class);

        $user = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],

            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
            ],
        );

        $user->forceFill([
            'is_super_admin' => true,
            'is_superadmin' => true,
            'is_admin' => true,
        ])->save();
    }
}
