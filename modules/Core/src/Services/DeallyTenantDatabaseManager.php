<?php

namespace Deally\Core\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Tenancy\TenantDatabaseManager;

class DeallyTenantDatabaseManager extends TenantDatabaseManager
{
    public function getCentralConnectionName(): string
    {
        return Config::get('database.default');
    }

    public function storedCentralConnectionName(): string
    {
        return Config::get('tenancy.central_connection_name') ?? Config::get('database.default');
    }

    public function getTenantConnectionName(Tenant $tenant): string
    {
        return 'tenant_'.$this->instanceFor($tenant);
    }

    public function getTenantDatabaseName(Tenant $tenant): string
    {
        return 'deally_tenant_'.$this->instanceFor($tenant);
    }

    public function createConnection(Tenant $tenant): void
    {
        $connectionName = $this->getTenantConnectionName($tenant);
        $databaseName = $this->getTenantDatabaseName($tenant);

        $tenantConfig = Config::get('database.connections.'.$this->storedCentralConnectionName()) ?? [];

        if (($tenantConfig['driver'] ?? null) === 'sqlite') {
            $directory = database_path('tenants');

            (new Filesystem)->ensureDirectoryExists($directory);

            $tenantConfig['database'] = $directory.DIRECTORY_SEPARATOR.$databaseName.'.sqlite';
        } else {
            $tenantConfig['database'] = $databaseName;
        }

        Config::set("database.connections.$connectionName", $tenantConfig);
    }

    public function switchToTenant(Tenant $tenant): void
    {
        if (Config::get('tenancy.central_connection_name') === null) {
            Config::set('tenancy.central_connection_name', Config::get('database.default'));
        }

        parent::switchToTenant($tenant);
    }

    public function restoreCentralConnection(): void
    {
        Config::set('database.default', $this->storedCentralConnectionName());
    }

    protected function instanceFor(Tenant $tenant): int
    {
        if ($tenant->instance !== null) {
            return (int) $tenant->instance;
        }

        $tenant->instance = (int) (Tenant::query()->max('instance') ?? 0) + 1;
        $tenant->save();

        return (int) $tenant->instance;
    }
}
