<?php

namespace Deally\Core\Services;

use Database\Seeders\DeallyTenantDatabaseSeeder;
use Illuminate\Support\Facades\Config;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Tenancy\TenantProvisioner;

class DeallyTenantProvisioner extends TenantProvisioner
{
    public function createTenantDatabase(Tenant $tenant): void
    {
        $this->createDatabase($tenant);

        $this->databaseManager->createConnection($tenant);
    }

    protected function runMigrations(Tenant $tenant): void
    {
        $connectionName = $this->databaseManager->getTenantConnectionName($tenant);

        $this->configureConnection($tenant);

        \Artisan::call('migrate', [
            '--database' => $connectionName,
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);

        $this->databaseManager->restoreCentralConnection();
    }

    public function seed(Tenant $tenant): void
    {
        $this->configureConnection($tenant);

        $connectionName = $this->databaseManager->getTenantConnectionName($tenant);

        Config::set('database.connections.deally', Config::get('database.connections.'.$connectionName));

        app('db')->purge('deally');

        \Artisan::call('db:seed', [
            '--database' => $connectionName,
            '--class' => DeallyTenantDatabaseSeeder::class,
            '--force' => true,
        ]);

        $this->databaseManager->restoreCentralConnection();
    }
}
