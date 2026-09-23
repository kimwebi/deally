<?php

use Illuminate\Support\Facades\Route;
use SaasFoundation\Http\Controllers\Auth\EmailVerificationController;
use SaasFoundation\Http\Controllers\Auth\ForgotPasswordController;
use SaasFoundation\Http\Controllers\Auth\LoginController;
use SaasFoundation\Http\Controllers\Auth\PasswordConfirmationController;
use SaasFoundation\Http\Controllers\Auth\ProfileController;
use SaasFoundation\Http\Controllers\Auth\RegisterController;
use SaasFoundation\Http\Controllers\Auth\ResetPasswordController;
use SaasFoundation\Http\Controllers\Auth\TwoFactorController;
use SaasFoundation\Http\Controllers\Central\CentralAuditController;
use SaasFoundation\Http\Controllers\Central\CentralDashboardController;
use SaasFoundation\Http\Controllers\Central\CentralFeatureController;
use SaasFoundation\Http\Controllers\Central\CentralPlanController;
use SaasFoundation\Http\Controllers\Central\CentralSetupController;
use SaasFoundation\Http\Controllers\Central\CentralSubscriptionController;
use SaasFoundation\Http\Controllers\Central\CentralTenantController;
use SaasFoundation\Http\Controllers\Central\CentralUserController;
use SaasFoundation\Http\Controllers\DashboardController;
use SaasFoundation\Http\Controllers\SwitchTenantController;
use SaasFoundation\Http\Controllers\Tenant\TenantAuditController;
use SaasFoundation\Http\Controllers\Tenant\TenantDashboardController;
use SaasFoundation\Http\Controllers\Tenant\TenantDomainController;
use SaasFoundation\Http\Controllers\Tenant\TenantInvitationController;
use SaasFoundation\Http\Controllers\Tenant\TenantPageController;
use SaasFoundation\Http\Controllers\Tenant\TenantProjectController;
use SaasFoundation\Http\Controllers\Tenant\TenantRoleController;
use SaasFoundation\Http\Controllers\Tenant\TenantSettingController;
use SaasFoundation\Http\Controllers\Tenant\TenantSubscriptionController;
use SaasFoundation\Http\Controllers\Tenant\TenantUsageController;
use SaasFoundation\Http\Controllers\Tenant\TenantUserController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
})->name('welcome');

/*
|--------------------------------------------------------------------------
| Auth Routes (Guest)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
    Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('register', [RegisterController::class, 'register']);

    Route::get('forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');

    Route::get('two-factor/challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
});

Route::post('logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Email Verification
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('verify-email/send', [EmailVerificationController::class, 'send'])->name('verification.send');
});

Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

/*
|--------------------------------------------------------------------------
| Password Confirmation
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('confirm-password', [PasswordConfirmationController::class, 'show'])->name('password.confirm');
    Route::post('confirm-password', [PasswordConfirmationController::class, 'confirm'])->name('password.confirm.post');
});

/*
|--------------------------------------------------------------------------
| Dashboard (Redirect)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->get('dashboard', DashboardController::class)->name('dashboard');

/*
|--------------------------------------------------------------------------
| Profile Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('profile/security', fn () => view('auth.profile', ['tab' => 'security', 'user' => auth()->user()]))->name('profile.security');
    Route::get('profile/two-factor', fn () => view('auth.profile', ['tab' => 'two-factor', 'user' => auth()->user()]))->name('profile.two-factor');
});

/*
|--------------------------------------------------------------------------
| Two-Factor Authentication Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('two-factor/setup', [TwoFactorController::class, 'show'])->name('two-factor.show');
    Route::post('two-factor/enable', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
    Route::post('two-factor/disable', [TwoFactorController::class, 'disable'])->name('two-factor.disable');
    Route::get('two-factor/codes', [TwoFactorController::class, 'codes'])->name('two-factor.codes');
    Route::post('two-factor/codes/regenerate', [TwoFactorController::class, 'regenerateCodes'])->name('two-factor.regenerate-codes');
});

/*
|--------------------------------------------------------------------------
| Tenant Switching
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->post('switch-tenant/{tenant}', [SwitchTenantController::class, 'switch'])->name('tenant.switch');

/*
|--------------------------------------------------------------------------
| Impersonation (Placeholder)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->post('impersonation/stop', fn () => back()->with('success', 'Impersonation stopped.'))->name('impersonation.stop');

/*
|--------------------------------------------------------------------------
| Invitation Accept (Public)
|--------------------------------------------------------------------------
*/

Route::get('invitations/{token}/accept', [TenantInvitationController::class, 'accept'])->name('invitations.accept');

/*
|--------------------------------------------------------------------------
| Central Admin Routes (Super Admin Only)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'super_admin'])->prefix('central')->name('central.')->group(function () {
    Route::get('/', [CentralDashboardController::class, 'index'])->name('dashboard');

    Route::resource('tenants', CentralTenantController::class)->except(['edit', 'update']);
    Route::put('tenants/{tenant}', [CentralTenantController::class, 'update'])->name('tenants.update');
    Route::get('tenants/{tenant}/edit', [CentralTenantController::class, 'edit'])->name('tenants.edit');
    Route::post('tenants/{tenant}/suspend', [CentralTenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('tenants/{tenant}/restore', [CentralTenantController::class, 'restore'])->name('tenants.restore');
    Route::post('tenants/{tenant}/clone', [CentralTenantController::class, 'clone'])->name('tenants.clone');

    Route::get('users', [CentralUserController::class, 'index'])->name('users.index');
    Route::get('users/{user}', [CentralUserController::class, 'show'])->name('users.show');
    Route::put('users/{user}', [CentralUserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [CentralUserController::class, 'destroy'])->name('users.destroy');

    Route::resource('plans', CentralPlanController::class);
    Route::post('plans/{plan}/set-default', [CentralPlanController::class, 'setDefault'])->name('plans.set-default');

    Route::resource('features', CentralFeatureController::class)->except(['show']);

    Route::get('subscriptions', [CentralSubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::get('subscriptions/{subscription}', [CentralSubscriptionController::class, 'show'])->name('subscriptions.show');
});

/*
|--------------------------------------------------------------------------
| Central Platform Operator Routes (Super Admin + Platform Support)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'platform.operator'])->prefix('central')->name('central.')->group(function () {
    Route::get('setup', [CentralSetupController::class, 'index'])->name('setup.index');
    Route::post('setup/tenants', [CentralSetupController::class, 'store'])->name('setup.tenants.store');
    Route::post('setup/tenants/{tenant}/provision', [CentralSetupController::class, 'provision'])->name('setup.tenants.provision');

    Route::get('audit', [CentralAuditController::class, 'index'])->name('audit.index');
});

/*
|--------------------------------------------------------------------------
| Tenant Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'tenancy.initialize', 'tenant.access'])->prefix('{tenant}')->name('tenant.')->group(function () {
    Route::get('/', [TenantDashboardController::class, 'index'])->name('dashboard');

    Route::get('users', [TenantUserController::class, 'index'])->name('users.index');
    Route::post('users', [TenantUserController::class, 'store'])->name('users.store');
    Route::get('users/{membership}', [TenantUserController::class, 'show'])->name('users.show');
    Route::put('users/{membership}', [TenantUserController::class, 'update'])->name('users.update');
    Route::delete('users/{membership}', [TenantUserController::class, 'destroy'])->name('users.destroy');

    Route::get('invitations', [TenantInvitationController::class, 'index'])->name('invitations.index');
    Route::get('invitations/create', [TenantInvitationController::class, 'create'])->name('invitations.create');
    Route::post('invitations', [TenantInvitationController::class, 'store'])->name('invitations.store');
    Route::get('invitations/{invitation}', [TenantInvitationController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{invitation}/resend', [TenantInvitationController::class, 'resend'])->name('invitations.resend');
    Route::post('invitations/{invitation}/revoke', [TenantInvitationController::class, 'revoke'])->name('invitations.revoke');

    Route::resource('roles', TenantRoleController::class)->except(['show']);
    Route::get('roles/{role}', [TenantRoleController::class, 'show'])->name('roles.show');

    Route::get('domains', [TenantDomainController::class, 'index'])->name('domains.index');
    Route::post('domains', [TenantDomainController::class, 'store'])->name('domains.store');
    Route::delete('domains/{domain}', [TenantDomainController::class, 'destroy'])->name('domains.destroy');
    Route::get('domains/{domain}/verify', [TenantDomainController::class, 'verify'])->name('domains.verify');
    Route::post('domains/{domain}/activate', [TenantDomainController::class, 'activate'])->name('domains.activate');

    Route::get('settings', [TenantSettingController::class, 'index'])->name('settings.index');
    Route::put('settings', [TenantSettingController::class, 'update'])->name('settings.update');

    Route::get('subscription', [TenantSubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('subscription/change-plan', [TenantSubscriptionController::class, 'changePlan'])->name('subscription.change-plan');
    Route::post('subscription/cancel', [TenantSubscriptionController::class, 'cancel'])->name('subscription.cancel');

    Route::get('usage', [TenantUsageController::class, 'index'])->name('usage.index');
    Route::get('audit', [TenantAuditController::class, 'index'])->name('audit.index');

    Route::resource('projects', TenantProjectController::class)->except(['show']);

    Route::resource('pages', TenantPageController::class)->except(['show']);
});

Route::middleware('tenancy.initialize')->prefix('{tenant}')->group(function () {
    Route::get('pages/{slug}', [TenantPageController::class, 'publicShow'])->name('tenant.pages.show');
});
