<?php

namespace App\Providers;

use Deally\Core\Http\Controllers\DocsController;
use Deally\Core\Http\Controllers\LegalController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The saas-foundation package registers a `{tenant}` catch-all during
        // its boot, which runs before this application's own routes load last.
        // Because routes are matched in registration order, bare public pages
        // (`/docs`, `/terms`, `/privacy`) would be captured by that catch-all
        // and guests bounced to the login page. Registering them here (during
        // the register phase, before any provider boots) keeps them first in
        // the route collection.
        Route::get('docs', [DocsController::class, 'index'])->name('docs.index');
        Route::get('terms', [LegalController::class, 'terms'])->name('legal.terms');
        Route::get('privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
    }

    public function boot(): void {}
}
