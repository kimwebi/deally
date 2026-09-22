<?php

namespace Deally\Core\Console\Commands;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Core\Services\DeallyTenantDatabaseManager;
use Deally\Core\Services\DeallyTenantProvisioner;
use Illuminate\Console\Command;
use SaasFoundation\Models\Tenant;

class ProvisionTenants extends Command
{
    protected $signature = 'deally:tenants:setup
                            {--tenant= : Tenant UUID or instance number to provision}
                            {--fresh : Drop and rebuild the tenant database instead of migrating}';

    protected $description = 'Seed central demo data, then create, migrate and seed tenant databases for active tenants';

    public function handle(): int
    {
        $this->seedCentralDemoData();

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

    /**
     * The command runs on a freshly migrated central database, so it seeds the
     * central demo tenants, users and roles first. That way tenant databases
     * are created and seeded against a known set of owners, and the regular
     * `db:seed` afterwards is still idempotent.
     */
    protected function seedCentralDemoData(): void
    {
        $this->info('Seeding central demo data (tenants, users, roles) ...');

        \Artisan::call('db:seed', [
            '--class' => DemoSeeder::class,
            '--force' => true,
        ]);

        \Artisan::call('db:seed', [
            '--class' => DeallyAccessSeeder::class,
            '--force' => true,
        ]);
    }
}
