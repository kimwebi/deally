<?php

namespace Deally\Core;

use Deally\Core\Console\Commands\MigrateTenants;
use Deally\Core\Console\Commands\ProvisionTenants;
use Deally\Core\Services\DeallyTenantDatabaseManager;
use Illuminate\Support\ServiceProvider;
use SaasFoundation\Services\Tenancy\TenantDatabaseManager;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantDatabaseManager::class, DeallyTenantDatabaseManager::class);
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
