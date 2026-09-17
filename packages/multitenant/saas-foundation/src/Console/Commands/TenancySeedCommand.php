<?php

namespace SaasFoundation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Tenancy\TenantDatabaseManager;

class TenancySeedCommand extends Command
{
    protected $signature = 'tenancy:seed
                            {--tenant= : Seed a single tenant by UUID or slug}
                            {--all : Seed all active tenants}
                            {--class= : The seeder class to run}';

    protected $description = 'Seed tenant databases';

    public function handle(): int
    {
        $tenantIdentifier = $this->option('tenant');
        $all = (bool) $this->option('all');

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
            $this->line("Seeding tenant '{$tenant->name}' ({$tenant->id})...");

            $databaseManager->createConnection($tenant);

            $connection = $databaseManager->getTenantConnectionName($tenant);

            $parameters = [
                '--database' => $connection,
                '--force' => true,
            ];

            if ($this->option('class') !== null) {
                $parameters['--class'] = $this->option('class');
            }

            Artisan::call('db:seed', $parameters);

            $databaseManager->restoreCentralConnection();

            $this->info(Artisan::output());
        }

        $this->info('Tenant seeding complete.');

        return self::SUCCESS;
    }

    protected function resolveTenant(string $identifier): ?Tenant
    {
        return Str::isUuid($identifier)
            ? Tenant::withTrashed()->find($identifier)
            : Tenant::withTrashed()->where('slug', $identifier)->first();
    }
}
