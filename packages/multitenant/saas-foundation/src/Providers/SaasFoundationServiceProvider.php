<?php

namespace SaasFoundation\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use SaasFoundation\Console\Commands\PermissionListCommand;
use SaasFoundation\Console\Commands\RoleListCommand;
use SaasFoundation\Console\Commands\SaasInstallCommand;
use SaasFoundation\Console\Commands\TenancyMigrateCommand;
use SaasFoundation\Console\Commands\TenancyRollbackCommand;
use SaasFoundation\Console\Commands\TenancyRunCommand;
use SaasFoundation\Console\Commands\TenancySeedCommand;
use SaasFoundation\Console\Commands\TenancyStatusCommand;
use SaasFoundation\Console\Commands\TenancyTestConnectionCommand;
use SaasFoundation\Console\Commands\TenantCreateCommand;
use SaasFoundation\Console\Commands\TenantDeleteCommand;
use SaasFoundation\Console\Commands\TenantDomainAddCommand;
use SaasFoundation\Console\Commands\TenantDomainRemoveCommand;
use SaasFoundation\Console\Commands\TenantDomainVerifyCommand;
use SaasFoundation\Console\Commands\TenantListCommand;
use SaasFoundation\Console\Commands\TenantRestoreCommand;
use SaasFoundation\Console\Commands\TenantResumeCommand;
use SaasFoundation\Console\Commands\TenantShowCommand;
use SaasFoundation\Console\Commands\TenantSuspendCommand;
use SaasFoundation\Console\Commands\TenantUpdateCommand;
use SaasFoundation\Console\Commands\TenantUserAddCommand;
use SaasFoundation\Console\Commands\TenantUserListCommand;
use SaasFoundation\Console\Commands\TenantUserRemoveCommand;
use SaasFoundation\Http\Middleware\EnsureEmailVerified;
use SaasFoundation\Http\Middleware\EnsureSuperAdmin;
use SaasFoundation\Http\Middleware\EnsureTenantMembership;
use SaasFoundation\Http\Middleware\EnsureUserIsActive;
use SaasFoundation\Http\Middleware\RequirePasswordConfirmation;
use SaasFoundation\Http\Middleware\TrackActivity;
use SaasFoundation\Services\Auditing\AuditService;
use SaasFoundation\Services\Tenancy\Contracts\TenantResolverInterface;
use SaasFoundation\Services\Tenancy\FeatureChecker;
use SaasFoundation\Services\Tenancy\LimitChecker;
use SaasFoundation\Services\Tenancy\Middleware\CheckFeature;
use SaasFoundation\Services\Tenancy\Middleware\CheckPermission;
use SaasFoundation\Services\Tenancy\Middleware\Impersonate;
use SaasFoundation\Services\Tenancy\Middleware\InitializeTenancy;
use SaasFoundation\Services\Tenancy\Middleware\PreventCrossTenantAccess;
use SaasFoundation\Services\Tenancy\Middleware\RequireActiveSubscription;
use SaasFoundation\Services\Tenancy\Resolvers\AuthUserResolver;
use SaasFoundation\Services\Tenancy\Resolvers\DomainResolver;
use SaasFoundation\Services\Tenancy\Resolvers\HeaderResolver;
use SaasFoundation\Services\Tenancy\Resolvers\QueryParamResolver;
use SaasFoundation\Services\Tenancy\Resolvers\RouteParameterResolver;
use SaasFoundation\Services\Tenancy\Resolvers\SessionResolver;
use SaasFoundation\Services\Tenancy\SubscriptionManager;
use SaasFoundation\Services\Tenancy\TenantContext;
use SaasFoundation\Services\Tenancy\TenantDatabaseManager;
use SaasFoundation\Services\Tenancy\TenantProvisioner;
use SaasFoundation\Services\Tenancy\TenantResolver;
use SaasFoundation\Services\Tenancy\TenantSettingsManager;

class SaasFoundationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Factory::guessFactoryNamesUsing(function (string $modelName) {
            return 'Database\\Factories\\'.Str::afterLast($modelName, '\\').'Factory';
        });

        Factory::guessModelNamesUsing(function (Factory $factory) {
            $model = Str::afterLast($factory::class, '\\');
            $model = Str::beforeLast($model, 'Factory');

            if (class_exists('App\\Models\\'.$model)) {
                return 'App\\Models\\'.$model;
            }

            return 'SaasFoundation\\Models\\'.$model;
        });

        $this->mergeConfigFrom(__DIR__.'/../../config/tenancy.php', 'tenancy');
        $this->mergeConfigFrom(__DIR__.'/../../config/saas.php', 'saas');

        $this->app->scoped(TenantContext::class, function () {
            return new TenantContext;
        });

        $this->app->singleton(TenantResolver::class, function ($app) {
            $resolver = new TenantResolver;

            $resolver->register('domain', $app->make(DomainResolver::class));
            $resolver->register('route_parameter', $app->make(RouteParameterResolver::class));
            $resolver->register('header', $app->make(HeaderResolver::class));
            $resolver->register('session', $app->make(SessionResolver::class));
            $resolver->register('auth_user', $app->make(AuthUserResolver::class));
            $resolver->register('query_param', $app->make(QueryParamResolver::class));

            return $resolver;
        });

        $this->app->singleton(TenantDatabaseManager::class);

        $this->app->singleton(TenantProvisioner::class);

        $this->app->scoped(TenantSettingsManager::class);

        $this->app->scoped(FeatureChecker::class);

        $this->app->scoped(LimitChecker::class);

        $this->app->scoped(SubscriptionManager::class);

        $this->app->singleton(AuditService::class);

        $this->app->bind(TenantResolverInterface::class, TenantResolver::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/tenancy.php' => config_path('tenancy.php'),
            __DIR__.'/../../config/saas.php' => config_path('saas.php'),
            __DIR__.'/../../config/billing.php' => config_path('billing.php'),
        ], 'saas-config');

        $this->publishesMigrations([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'saas-migrations');

        $this->publishes([
            __DIR__.'/../../resources/css' => public_path('vendor/multitenant/saas-foundation/css'),
        ], 'saas-assets');

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'saas');

        $this->app['view']->addLocation(__DIR__.'/../../resources/views');

        $this->registerMiddleware();

        $this->registerRoutes();

        $this->registerCommands();
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];

        $router->aliasMiddleware('tenancy.initialize', InitializeTenancy::class);
        $router->aliasMiddleware('tenancy.active_subscription', RequireActiveSubscription::class);
        $router->aliasMiddleware('tenancy.check_feature', CheckFeature::class);
        $router->aliasMiddleware('tenancy.check_permission', CheckPermission::class);
        $router->aliasMiddleware('tenancy.impersonate', Impersonate::class);
        $router->aliasMiddleware('tenancy.prevent_cross_tenant', PreventCrossTenantAccess::class);

        $router->aliasMiddleware('track.activity', TrackActivity::class);
        $router->aliasMiddleware('super_admin', EnsureSuperAdmin::class);
        $router->aliasMiddleware('tenant.access', EnsureTenantMembership::class);
        $router->aliasMiddleware('email.verified', EnsureEmailVerified::class);
        $router->aliasMiddleware('user.active', EnsureUserIsActive::class);
        $router->aliasMiddleware('password.confirm', RequirePasswordConfirmation::class);
    }

    protected function registerRoutes(): void
    {
        Route::middleware('web')->group(function (): void {
            $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        });

        Route::middleware(['api', 'track.activity'])->prefix('api')->group(function (): void {
            $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        });
    }

    protected function registerCommands(): void
    {
        $this->commands([
            PermissionListCommand::class,
            RoleListCommand::class,
            SaasInstallCommand::class,
            TenancyMigrateCommand::class,
            TenancyRollbackCommand::class,
            TenancyRunCommand::class,
            TenancySeedCommand::class,
            TenancyStatusCommand::class,
            TenancyTestConnectionCommand::class,
            TenantCreateCommand::class,
            TenantDeleteCommand::class,
            TenantDomainAddCommand::class,
            TenantDomainRemoveCommand::class,
            TenantDomainVerifyCommand::class,
            TenantListCommand::class,
            TenantRestoreCommand::class,
            TenantResumeCommand::class,
            TenantShowCommand::class,
            TenantSuspendCommand::class,
            TenantUpdateCommand::class,
            TenantUserAddCommand::class,
            TenantUserListCommand::class,
            TenantUserRemoveCommand::class,
        ]);
    }
}
