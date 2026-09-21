<?php

use Deally\Core\Http\Controllers\ActivityController;
use Deally\Core\Http\Controllers\DocsController;
use Deally\Core\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('docs/ai', [DocsController::class, 'ai'])->name('docs.ai');
Route::get('docs/overview', [DocsController::class, 'overview'])->name('docs.overview');

Route::middleware('deally')
    ->prefix('app')
    ->name('deally.')
    ->group(function (): void {
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

        Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');
    });
