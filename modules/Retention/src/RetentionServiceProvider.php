<?php

namespace Deally\Retention;

use Deally\Retention\Services\RetentionService;
use Illuminate\Support\ServiceProvider;

class RetentionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RetentionService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'retention');
    }
}
