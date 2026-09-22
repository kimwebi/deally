<?php

namespace Deally\Solutions;

use Illuminate\Support\ServiceProvider;

class SolutionsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'solutions');
    }
}
