<?php

namespace Deally\Calls;

use Deally\Calls\Services\AssistantFactory;
use Illuminate\Support\ServiceProvider;

class CallsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // A single factory per request so driver resolution and its validation
        // happen once, and every call site agrees on the active provider.
        $this->app->singleton(AssistantFactory::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'calls');
    }
}
