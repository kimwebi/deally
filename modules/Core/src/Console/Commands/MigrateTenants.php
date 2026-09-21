<?php

namespace Deally\Core\Console\Commands;

use Deally\Core\Services\DeallyTenantDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use SaasFoundation\Models\Tenant;

class MigrateTenants extends Command
{
    protected $signature = 'tenant:migrate
                            {--tenant= : Tenant slug, UUID, or instance to migrate}';

    protected $description = 'Apply only the pending tenant database migrations for active tenants';

    public function handle(): int
    {
        $manager = app(DeallyTenantDatabaseManager::class);

        $query = Tenant::query()->active()->orderBy('created_at');

        if ($tenant = $this->option('tenant')) {
            $query->where(function ($where) use ($tenant) {
                $where->where('id', $tenant)
                    ->orWhere('slug', $tenant)
                    ->orWhere('instance', (int) $tenant);
            });
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->error('No active tenant matched.');

            return self::FAILURE;
        }

        $failed = false;

        foreach ($tenants as $tenant) {
            $manager->createConnection($tenant);

            $connection = $manager->getTenantConnectionName($tenant);

            $this->info("Migrating pending migrations for {$tenant->name} ({$connection}) ...");

            try {
                Artisan::call('migrate', [
                    '--database' => $connection,
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ]);

                $output = trim(Artisan::output());

                if ($output !== '') {
                    $this->line($output);
                }
            } catch (\Throwable $e) {
                $this->error("Migration failed for {$tenant->name}: {$e->getMessage()}");
                $failed = true;
            } finally {
                $manager->restoreCentralConnection();
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
