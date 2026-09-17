<?php

use Deally\TenantManagement\Http\Controllers\TenantManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::middleware('auth')->group(function () {
        Route::get('/admin/tenants', [TenantManagementController::class, 'index'])
            ->name('tenant-management.index');

        Route::post('/admin/tenants', [TenantManagementController::class, 'store'])
            ->name('tenant-management.store');

        Route::post('/admin/tenants/{tenant}/clone', [TenantManagementController::class, 'clone'])
            ->name('tenant-management.clone')
            ->whereUuid('tenant');
    });
});
