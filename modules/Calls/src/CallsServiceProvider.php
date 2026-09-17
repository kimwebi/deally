<?php

namespace Deally\Calls;

use Illuminate\Support\ServiceProvider;

class CallsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'calls');
    }
}
