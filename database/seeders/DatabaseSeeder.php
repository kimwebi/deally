<?php

namespace Database\Seeders;

use Deally\Core\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            // DemoSeeder::class,
            DeallyAccessSeeder::class,
        ]);

        $user = User::query()->firstOrCreate(
            ['email' => 'tech@wyzone.com'],

            [
                'name' => 'Wyzone Labs',
                'password' => Hash::make('password'),
            ],
        );

        $user->forceFill([
            'is_super_admin' => true,
        ])->save();
    }
}
