<?php

namespace SaasFoundation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Tenancy\TenantDatabaseManager;

class TenancyRollbackCommand extends Command
{
    protected $signature = 'tenancy:rollback
                            {--tenant= : Rollback a single tenant by UUID or slug}
                            {--all : Rollback all active tenants}
                            {--steps=1 : Number of batches to rollback}';

    protected $description = 'Rollback tenant database migrations';

    public function handle(): int
    {
        $tenantIdentifier = $this->option('tenant');
        $all = (bool) $this->option('all');
        $steps = (int) $this->option('steps');

        if (($tenantIdentifier !== null && $all) || ($tenantIdentifier === null && ! $all)) {
            $this->error('Provide exactly one of --tenant or --all.');

            return self::FAILURE;
        }

        $tenants = $all
            ? Tenant::active()->get()
            : [$this->resolveTenant($tenantIdentifier)];

        if ($tenants[0] === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $databaseManager = app(TenantDatabaseManager::class);

        foreach ($tenants as $tenant) {
            $this->line("Rolling back tenant '{$tenant->name}' ({$tenant->id})...");

            $databaseManager->createConnection($tenant);

            $connection = $databaseManager->getTenantConnectionName($tenant);

            Artisan::call('migrate:rollback', [
                '--database' => $connection,
                '--step' => $steps,
                '--force' => true,
            ]);

            $databaseManager->restoreCentralConnection();

            $this->info(Artisan::output());
        }

        $this->info('Tenant rollback complete.');

        return self::SUCCESS;
    }

    protected function resolveTenant(string $identifier): ?Tenant
    {
        return Str::isUuid($identifier)
            ? Tenant::withTrashed()->find($identifier)
            : Tenant::withTrashed()->where('slug', $identifier)->first();
    }
}
