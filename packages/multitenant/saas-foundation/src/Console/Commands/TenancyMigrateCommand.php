<?php

namespace SaasFoundation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Tenancy\TenantDatabaseManager;

class TenancyMigrateCommand extends Command
{
    protected $signature = 'tenancy:migrate
                            {--tenant= : Migrate a single tenant by UUID or slug}
                            {--all : Migrate all active tenants}
                            {--fresh : Drop all tables and re-run migrations}';

    protected $description = 'Run tenant database migrations';

    public function handle(): int
    {
        $tenantIdentifier = $this->option('tenant');
        $all = (bool) $this->option('all');
        $fresh = (bool) $this->option('fresh');

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
            $this->line("Migrating tenant '{$tenant->name}' ({$tenant->id})...");

            $databaseManager->createConnection($tenant);

            $connection = $databaseManager->getTenantConnectionName($tenant);

            $command = $fresh ? 'migrate:fresh' : 'migrate';

            Artisan::call($command, [
                '--database' => $connection,
                '--force' => true,
            ]);

            $databaseManager->restoreCentralConnection();

            $this->info(Artisan::output());
        }

        $this->info('Tenant migrations complete.');

        return self::SUCCESS;
    }

    protected function resolveTenant(string $identifier): ?Tenant
    {
        return Str::isUuid($identifier)
            ? Tenant::withTrashed()->find($identifier)
            : Tenant::withTrashed()->where('slug', $identifier)->first();
    }
}
