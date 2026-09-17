<?php

namespace Deally\Core\Services;

use Illuminate\Support\Facades\Config;
use SaasFoundation\Models\Tenant;

class TenantConnectionBinder
{
    public function __construct(protected DeallyTenantDatabaseManager $databaseManager) {}

    public function bind(Tenant $tenant): void
    {
        $manager = $this->databaseManager;
        $connection = $manager->getTenantConnectionName($tenant);

        $manager->createConnection($tenant);

        Config::set('database.connections.deally', Config::get('database.connections.'.$connection));

        app('db')->purge('deally');
    }

    public function unbind(): void
    {
        Config::set('database.connections.deally', null);

        app('db')->purge('deally');
    }
}
