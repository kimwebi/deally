<?php

namespace SaasFoundation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Tenancy\TenantDatabaseManager;

class TenancyTestConnectionCommand extends Command
{
    protected $signature = 'tenancy:test-connection
                            {--tenant= : The tenant UUID or slug (required)}';

    protected $description = 'Test a tenant database connection';

    public function handle(): int
    {
        $identifier = $this->option('tenant');

        if ($identifier === null || $identifier === '') {
            $this->error('The --tenant option is required.');

            return self::FAILURE;
        }

        $tenant = Str::isUuid($identifier)
            ? Tenant::withTrashed()->find($identifier)
            : Tenant::withTrashed()->where('slug', $identifier)->first();

        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $databaseManager = app(TenantDatabaseManager::class);

        $this->components->twoColumnDetail('Tenant', $tenant->name);
        $this->components->twoColumnDetail('Connection', $databaseManager->getTenantConnectionName($tenant));
        $this->components->twoColumnDetail('Database', $databaseManager->getTenantDatabaseName($tenant));

        try {
            $databaseManager->createConnection($tenant);
            $pdo = $databaseManager->getPdo($tenant);

            if ($pdo === null) {
                $this->components->error('Unable to establish a connection to the tenant database.');

                return self::FAILURE;
            }

            $this->components->twoColumnDetail('Status', 'Connected', '<info>OK</info>');
            $this->components->twoColumnDetail('Server', $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->components->error('Connection failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
