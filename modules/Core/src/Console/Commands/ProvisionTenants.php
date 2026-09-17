<?php

namespace Deally\Core\Console\Commands;

use Deally\Core\Services\DeallyTenantDatabaseManager;
use Deally\Core\Services\DeallyTenantProvisioner;
use Illuminate\Console\Command;
use SaasFoundation\Models\Tenant;

class ProvisionTenants extends Command
{
    protected $signature = 'deally:tenants:setup
                            {--tenant= : Tenant UUID or instance number to provision}
                            {--fresh : Drop and rebuild the tenant database instead of migrating}';

    protected $description = 'Create, migrate and seed tenant databases for active tenants';

    public function handle(): int
    {
        $provisioner = app(DeallyTenantProvisioner::class);

        $query = Tenant::query()->active()->orderBy('created_at');

        if ($tenant = $this->option('tenant')) {
            $query->where(function ($w) use ($tenant) {
                $w->where('id', $tenant)->orWhere('instance', (int) $tenant);
            });
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->error('No active tenant matched.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $this->info("Provisioning {$tenant->name} ...");

            $manager = app(DeallyTenantDatabaseManager::class);

            if ($this->option('fresh')) {
                $provisioner->createTenantDatabase($tenant);

                \Artisan::call('migrate:fresh', [
                    '--database' => $manager->getTenantConnectionName($tenant),
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ]);
            } else {
                $provisioner->migrate($tenant);
            }

            $provisioner->seed($tenant);

            $this->info("  -> {$manager->getTenantDatabaseName($tenant)} ready");
        }

        return self::SUCCESS;
    }
}
