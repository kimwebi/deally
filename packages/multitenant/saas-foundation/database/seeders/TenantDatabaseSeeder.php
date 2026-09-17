<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\TenantSetting;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = $this->resolveTenant();

        if ($tenant === null) {
            Log::info('Tenant database seeded (no tenant context)');

            return;
        }

        TenantSetting::updateOrCreate(
            ['tenant_id' => $tenant->id, 'key' => 'timezone'],
            ['value' => $tenant->timezone ?? 'UTC', 'type' => 'string'],
        );

        Log::info('Tenant database seeded', ['tenant_id' => $tenant->id]);
    }

    private function resolveTenant(): ?Tenant
    {
        if (app()->bound('tenant')) {
            return app('tenant');
        }

        if (method_exists(Tenant::class, 'current') && Tenant::current() !== null) {
            return Tenant::current();
        }

        return null;
    }
}
