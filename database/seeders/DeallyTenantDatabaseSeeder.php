<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DeallyTenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DeallyDemoSeeder::class);
    }
}
