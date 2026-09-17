<?php

use Deally\Settings\Http\Controllers\RoleController;
use Deally\Settings\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('deally')
    ->prefix('app')
    ->name('deally.')
    ->group(function (): void {
        Route::get('account', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('account', [SettingsController::class, 'update'])->name('settings.update');

        Route::prefix('admin')
            ->name('roles.')
            ->group(function (): void {
                Route::get('roles', [RoleController::class, 'index'])->name('index');
                Route::get('roles/create', [RoleController::class, 'create'])->name('create');
                Route::post('roles', [RoleController::class, 'store'])->name('store');
                Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('edit');
                Route::put('roles/{role}', [RoleController::class, 'update'])->name('update');
                Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('destroy');
            });
    });
