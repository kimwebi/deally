<?php

namespace Deally\Core;

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

        $this->commands([
            ProvisionTenants::class,
        ]);
    }
}
