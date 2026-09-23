<?php

use Deally\Settings\Http\Controllers\IntegrationsController;
use Deally\Settings\Http\Controllers\RoleController;
use Deally\Settings\Http\Controllers\SettingsController;
use Deally\Settings\Http\Controllers\TeamController;
use Deally\Settings\Http\Controllers\UserController;
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

        Route::prefix('admin')
            ->name('teams.')
            ->group(function (): void {
                Route::get('teams', [TeamController::class, 'index'])->name('index');
                Route::post('teams', [TeamController::class, 'store'])->name('store');
                Route::put('teams/{team}', [TeamController::class, 'update'])->name('update');
                Route::delete('teams/{team}', [TeamController::class, 'destroy'])->name('destroy');
            });

        Route::prefix('admin')
            ->name('users.')
            ->group(function (): void {
                Route::get('users', [UserController::class, 'index'])->name('index');
                Route::post('users', [UserController::class, 'store'])->name('store');
                Route::put('users/{membership}', [UserController::class, 'update'])->name('update');
                Route::delete('users/{membership}', [UserController::class, 'destroy'])->name('destroy');
            });

        Route::get('admin/integrations', [IntegrationsController::class, 'index'])->name('integrations.index');
        Route::post('admin/integrations', [IntegrationsController::class, 'update'])->name('integrations.update');
    });
