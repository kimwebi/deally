<?php

use Illuminate\Support\Facades\Route;
use SaasFoundation\Http\Controllers\Central\CentralAuditController;
use SaasFoundation\Http\Controllers\Central\CentralFeatureController;
use SaasFoundation\Http\Controllers\Central\CentralPlanController;
use SaasFoundation\Http\Controllers\Central\CentralSubscriptionController;
use SaasFoundation\Http\Controllers\Central\CentralTenantController;
use SaasFoundation\Http\Controllers\Central\CentralUserController;
use SaasFoundation\Http\Controllers\Tenant\TenantAuditController;
use SaasFoundation\Http\Controllers\Tenant\TenantDomainController;
use SaasFoundation\Http\Controllers\Tenant\TenantInvitationController;
use SaasFoundation\Http\Controllers\Tenant\TenantProjectController;
use SaasFoundation\Http\Controllers\Tenant\TenantRoleController;
use SaasFoundation\Http\Controllers\Tenant\TenantSettingController;
use SaasFoundation\Http\Controllers\Tenant\TenantSubscriptionController;
use SaasFoundation\Http\Controllers\Tenant\TenantUserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api by the bootstrap configuration.
| Tenant-scoped routes use a {tenant} UUID parameter.
|
*/

Route::prefix('v1')->group(function () {

    Route::get('health', fn () => response()->json(['status' => 'ok']));

    /*
    |------------------------------------------------------------------
    | Central Admin API Routes
    |------------------------------------------------------------------
    */

    Route::middleware(['auth:sanctum', 'super_admin'])->prefix('central')->name('api.central.')->group(function () {
        Route::get('tenants', [CentralTenantController::class, 'index'])->name('tenants.index');
        Route::post('tenants', [CentralTenantController::class, 'store'])->name('tenants.store');
        Route::get('tenants/{tenant}', [CentralTenantController::class, 'show'])->name('tenants.show');
        Route::put('tenants/{tenant}', [CentralTenantController::class, 'update'])->name('tenants.update');
        Route::delete('tenants/{tenant}', [CentralTenantController::class, 'destroy'])->name('tenants.destroy');
        Route::post('tenants/{tenant}/suspend', [CentralTenantController::class, 'suspend'])->name('tenants.suspend');
        Route::post('tenants/{tenant}/restore', [CentralTenantController::class, 'restore'])->name('tenants.restore');

        Route::get('users', [CentralUserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [CentralUserController::class, 'show'])->name('users.show');
        Route::put('users/{user}', [CentralUserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [CentralUserController::class, 'destroy'])->name('users.destroy');

        Route::get('plans', [CentralPlanController::class, 'index'])->name('plans.index');
        Route::post('plans', [CentralPlanController::class, 'store'])->name('plans.store');
        Route::get('plans/{plan}', [CentralPlanController::class, 'show'])->name('plans.show');
        Route::put('plans/{plan}', [CentralPlanController::class, 'update'])->name('plans.update');
        Route::delete('plans/{plan}', [CentralPlanController::class, 'destroy'])->name('plans.destroy');

        Route::get('features', [CentralFeatureController::class, 'index'])->name('features.index');
        Route::post('features', [CentralFeatureController::class, 'store'])->name('features.store');
        Route::put('features/{feature}', [CentralFeatureController::class, 'update'])->name('features.update');
        Route::delete('features/{feature}', [CentralFeatureController::class, 'destroy'])->name('features.destroy');

        Route::get('subscriptions', [CentralSubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::get('subscriptions/{subscription}', [CentralSubscriptionController::class, 'show'])->name('subscriptions.show');

        Route::get('audit', [CentralAuditController::class, 'index'])->name('audit.index');
    });

    /*
    |------------------------------------------------------------------
    | Tenant-Scoped API Routes
    |------------------------------------------------------------------
    */

    Route::middleware(['auth:sanctum', 'tenancy.initialize', 'tenant.access'])->prefix('{tenant}')->name('api.tenant.')->group(function () {
        Route::get('users', [TenantUserController::class, 'index'])->name('users.index');
        Route::post('users', [TenantUserController::class, 'store'])->name('users.store');
        Route::get('users/{membership}', [TenantUserController::class, 'show'])->name('users.show');
        Route::put('users/{membership}', [TenantUserController::class, 'update'])->name('users.update');
        Route::delete('users/{membership}', [TenantUserController::class, 'destroy'])->name('users.destroy');

        Route::get('invitations', [TenantInvitationController::class, 'index'])->name('invitations.index');
        Route::post('invitations', [TenantInvitationController::class, 'store'])->name('invitations.store');
        Route::get('invitations/{invitation}', [TenantInvitationController::class, 'show'])->name('invitations.show');
        Route::post('invitations/{invitation}/resend', [TenantInvitationController::class, 'resend'])->name('invitations.resend');
        Route::post('invitations/{invitation}/revoke', [TenantInvitationController::class, 'revoke'])->name('invitations.revoke');

        Route::get('roles', [TenantRoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [TenantRoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}', [TenantRoleController::class, 'show'])->name('roles.show');
        Route::put('roles/{role}', [TenantRoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [TenantRoleController::class, 'destroy'])->name('roles.destroy');

        Route::get('domains', [TenantDomainController::class, 'index'])->name('domains.index');
        Route::post('domains', [TenantDomainController::class, 'store'])->name('domains.store');
        Route::delete('domains/{domain}', [TenantDomainController::class, 'destroy'])->name('domains.destroy');
        Route::post('domains/{domain}/verify', [TenantDomainController::class, 'activate'])->name('domains.verify');

        Route::get('settings', [TenantSettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [TenantSettingController::class, 'update'])->name('settings.update');

        Route::get('subscription', [TenantSubscriptionController::class, 'index'])->name('subscription.index');
        Route::post('subscription/change-plan', [TenantSubscriptionController::class, 'changePlan'])->name('subscription.change-plan');
        Route::post('subscription/cancel', [TenantSubscriptionController::class, 'cancel'])->name('subscription.cancel');

        Route::get('audit', [TenantAuditController::class, 'index'])->name('audit.index');

        Route::get('projects', [TenantProjectController::class, 'index'])->name('projects.index');
        Route::post('projects', [TenantProjectController::class, 'store'])->name('projects.store');
        Route::get('projects/{project}', [TenantProjectController::class, 'edit'])->name('projects.show');
        Route::put('projects/{project}', [TenantProjectController::class, 'update'])->name('projects.update');
        Route::delete('projects/{project}', [TenantProjectController::class, 'destroy'])->name('projects.destroy');
    });
});
