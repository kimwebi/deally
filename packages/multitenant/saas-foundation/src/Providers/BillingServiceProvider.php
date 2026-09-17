<?php

namespace SaasFoundation\Providers;

use Illuminate\Support\ServiceProvider;
use SaasFoundation\Services\Billing\BillingService;
use SaasFoundation\Services\Billing\Contracts\BillingProviderInterface;
use SaasFoundation\Services\Billing\FeatureManager;
use SaasFoundation\Services\Billing\PlanManager;
use SaasFoundation\Services\Billing\Providers\NullBillingProvider;
use SaasFoundation\Services\Billing\UsageTracker;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BillingService::class);
        $this->app->singleton(PlanManager::class);
        $this->app->singleton(FeatureManager::class);
        $this->app->singleton(UsageTracker::class);

        $this->app->singleton(BillingProviderInterface::class, function ($app) {
            $driver = config('billing.driver', 'null');
            $providerClass = config("billing.providers.{$driver}", NullBillingProvider::class);

            return $app->make($providerClass);
        });
    }

    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/billing.php', 'billing');
    }
}
