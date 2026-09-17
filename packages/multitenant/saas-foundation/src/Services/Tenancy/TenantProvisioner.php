<?php

namespace SaasFoundation\Services\Tenancy;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SaasFoundation\Models\Domain;
use SaasFoundation\Models\Tenant;

class TenantProvisioner
{
    public function __construct(
        protected TenantDatabaseManager $databaseManager
    ) {}

    public function provision(array $data): Tenant
    {
        $tenant = Tenant::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'status' => Tenant::STATUS_PROVISIONING,
            'timezone' => $data['timezone'] ?? 'UTC',
            'locale' => $data['locale'] ?? 'en',
            'currency' => $data['currency'] ?? 'USD',
            'metadata' => $data['metadata'] ?? [],
        ]);

        $this->createDatabase($tenant);
        $this->configureConnection($tenant);
        $this->runMigrations($tenant);
        $this->seed($tenant);
        $this->createDefaultDomain($tenant);

        $this->setStatus($tenant, Tenant::STATUS_ACTIVE);

        return $tenant;
    }

    public function cloneForTroubleshooting(Tenant $source, array $data = []): Tenant
    {
        $clone = Tenant::create([
            'name' => $data['name'] ?? $source->name.' (Clone)',
            'slug' => $this->uniqueSlug($source->slug),
            'status' => Tenant::STATUS_PROVISIONING,
            'timezone' => $source->timezone ?? 'UTC',
            'locale' => $source->locale ?? 'en',
            'currency' => $source->currency ?? 'USD',
            'metadata' => array_merge((array) $source->metadata, [
                'cloned_from' => $source->id,
                'clone_purpose' => 'troubleshooting',
            ]),
            'settings' => $source->settings ?? [],
        ]);

        $this->createDatabase($clone);
        $this->configureConnection($clone);
        $this->runMigrations($clone);
        $this->cloneDatabaseData($source, $clone);
        $this->createDefaultDomain($clone);

        $this->setStatus($clone, Tenant::STATUS_ACTIVE);

        return $clone;
    }

    public function delete(Tenant $tenant): bool
    {
        $this->setStatus($tenant, Tenant::STATUS_INACTIVE);

        $tenant->domains()->delete();
        $tenant->memberships()->delete();
        $tenant->roles()->delete();
        $tenant->settings()->delete();
        $tenant->subscriptions()->delete();

        $this->databaseManager->deleteConnection($tenant);

        return $tenant->delete();
    }

    public function restore(Tenant $tenant): bool
    {
        $restored = $tenant->restore();

        if ($restored) {
            $this->setStatus($tenant, Tenant::STATUS_ACTIVE);
        }

        return $restored;
    }

    public function migrate(Tenant $tenant): void
    {
        $this->createDatabase($tenant);
        $this->configureConnection($tenant);
        $this->runMigrations($tenant);
    }

    public function migrateAll(): void
    {
        $tenants = Tenant::query()->active()->get();

        foreach ($tenants as $tenant) {
            $this->migrate($tenant);
        }
    }

    public function rollback(Tenant $tenant, int $steps = 1): void
    {
        $this->configureConnection($tenant);

        $connectionName = $this->databaseManager->getTenantConnectionName($tenant);

        \Artisan::call('migrate:rollback', [
            '--database' => $connectionName,
            '--step' => $steps,
        ]);
    }

    public function seed(Tenant $tenant): void
    {
        $this->configureConnection($tenant);

        $connectionName = $this->databaseManager->getTenantConnectionName($tenant);

        \Artisan::call('db:seed', [
            '--database' => $connectionName,
            '--force' => true,
        ]);
    }

    protected function createDatabase(Tenant $tenant): void
    {
        $databaseName = $this->databaseManager->getTenantDatabaseName($tenant);
        $centralConnection = Config::get('database.default');

        $driver = Config::get("database.connections.{$centralConnection}.driver");

        if ($driver === 'mysql') {
            DB::connection($centralConnection)->statement(
                "CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
        } elseif ($driver === 'pgsql') {
            $dbExists = DB::connection($centralConnection)->select(
                'SELECT 1 FROM pg_database WHERE datname = ?',
                [$databaseName]
            );

            if (empty($dbExists)) {
                DB::connection($centralConnection)->statement("CREATE DATABASE \"{$databaseName}\"");
            }
        } elseif ($driver === 'sqlite') {
            $path = database_path('tenants/'.$databaseName.'.sqlite');

            if (! file_exists($path)) {
                touch($path);
            }
        }
    }

    protected function configureConnection(Tenant $tenant): void
    {
        $this->databaseManager->createConnection($tenant);
        $this->databaseManager->switchToTenant($tenant);
    }

    protected function runMigrations(Tenant $tenant): void
    {
        $connectionName = $this->databaseManager->getTenantConnectionName($tenant);

        \Artisan::call('migrate', [
            '--database' => $connectionName,
            '--force' => true,
        ]);

        $this->databaseManager->restoreCentralConnection();
    }

    protected function createDefaultDomain(Tenant $tenant): void
    {
        $baseDomain = config('tenancy.domain.base', 'localhost');

        $domain = "{$tenant->slug}.{$baseDomain}";

        Domain::create([
            'tenant_id' => $tenant->id,
            'domain' => $domain,
            'type' => Domain::TYPE_SUBDOMAIN,
            'is_primary' => true,
            'is_verified' => true,
            'is_active' => true,
        ]);
    }

    protected function setStatus(Tenant $tenant, string $status): void
    {
        $tenant->update(['status' => $status]);
    }

    protected function uniqueSlug(string $slug): string
    {
        $candidate = $slug;
        $suffix = 2;

        while (Tenant::query()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    protected function cloneDatabaseData(Tenant $source, Tenant $clone): void
    {
        $this->configureConnection($clone);

        if (! $this->databaseManager->isInstalled($source)) {
            return;
        }

        $sourcePdo = $this->databaseManager->getPdo($source);

        if ($sourcePdo === null) {
            return;
        }

        $connectionName = $this->databaseManager->getTenantConnectionName($clone);
        $clonePdo = DB::connection($connectionName)->getPdo();

        $tables = $this->databaseManager->getTenantTables($sourcePdo);

        foreach ($tables as $table) {
            $rows = $sourcePdo->query("SELECT * FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);
            $clonePdo->query("DELETE FROM `{$table}`");

            foreach ($rows as $row) {
                $columnList = implode(', ', array_map(fn ($column) => "`{$column}`", array_keys($row)));
                $placeholders = implode(', ', array_fill(0, count($row), '?'));
                $statement = $clonePdo->prepare("INSERT INTO `{$table}` ({$columnList}) VALUES ({$placeholders})");
                $statement->execute(array_values($row));
            }
        }

        $this->databaseManager->restoreCentralConnection();
    }
}
