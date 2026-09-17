<?php

namespace Deally\TenantManagement;

use Deally\TenantManagement\Contracts\TenantInstanceManager;
use Deally\TenantManagement\Models\Tenant;
use Deally\TenantManagement\Policies\TenantPolicy;
use Deally\TenantManagement\Services\KimwebiTenantInstanceManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class TenantManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TenantInstanceManager::class, KimwebiTenantInstanceManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        Gate::policy(Tenant::class, TenantPolicy::class);
    }
}
