<?php

namespace Deally\Core;

use Deally\Core\Console\Commands\MigrateTenants;
use Deally\Core\Console\Commands\ProvisionTenants;
use Deally\Core\Services\DeallyTenantDatabaseManager;
use Deally\Core\Services\DeallyTenantProvisioner;
use Illuminate\Support\ServiceProvider;
use SaasFoundation\Services\Tenancy\TenantDatabaseManager;
use SaasFoundation\Services\Tenancy\TenantProvisioner;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantDatabaseManager::class, DeallyTenantDatabaseManager::class);
        $this->app->singleton(TenantProvisioner::class, DeallyTenantProvisioner::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'core');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->commands([
            MigrateTenants::class,
            ProvisionTenants::class,
        ]);
    }
}
