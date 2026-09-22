<?php

use Deally\Workspace\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware('deally')
    ->prefix('app')
    ->name('deally.')
    ->group(function (): void {
        Route::get('home', [WorkspaceController::class, 'index'])->name('workspace');
        Route::post('home/event', [WorkspaceController::class, 'storeEvent'])->name('workspace.event');
    });
