<?php

namespace App\Providers;

use Deally\Core\Http\Controllers\DocsController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The saas-foundation package registers a `{tenant}` catch-all during
        // its boot, which runs before this application's own routes load last.
        // Because routes are matched in registration order, bare `/docs` would
        // be captured by that catch-all and guests bounced to the login page.
        // Registering it here (during the register phase, before any provider
        // boots) keeps the public docs page first in the route collection.
        Route::get('docs', [DocsController::class, 'index'])->name('docs.index');
    }

    public function boot(): void {}
}
