<?php

namespace SaasFoundation\Services\Tenancy;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use SaasFoundation\Models\Tenant;

class TenantDatabaseManager
{
    public function createConnection(Tenant $tenant): void
    {
        $connectionName = $this->getTenantConnectionName($tenant);
        $databaseName = $this->getTenantDatabaseName($tenant);

        $centralConfig = Config::get('database.connections.'.$this->getCentralConnectionName());

        $tenantConfig = [
            'driver' => $centralConfig['driver'],
            'host' => $centralConfig['host'] ?? '127.0.0.1',
            'port' => $centralConfig['port'] ?? 3306,
            'database' => $databaseName,
            'username' => $centralConfig['username'] ?? '',
            'password' => $centralConfig['password'] ?? '',
            'charset' => $centralConfig['charset'] ?? 'utf8mb4',
            'collation' => $centralConfig['collation'] ?? 'utf8mb4_unicode_ci',
            'prefix' => $centralConfig['prefix'] ?? '',
            'prefix_indexes' => $centralConfig['prefix_indexes'] ?? true,
            'strict' => $centralConfig['strict'] ?? true,
            'engine' => $centralConfig['engine'] ?? null,
        ];

        if (isset($centralConfig['unix_socket'])) {
            $tenantConfig['unix_socket'] = $centralConfig['unix_socket'];
        }

        if (isset($centralConfig['ssl_mode'])) {
            $tenantConfig['ssl_mode'] = $centralConfig['ssl_mode'];
        }

        if (isset($centralConfig['options'])) {
            $tenantConfig['options'] = $centralConfig['options'];
        }

        Config::set("database.connections.{$connectionName}", $tenantConfig);
    }

    public function deleteConnection(Tenant $tenant): void
    {
        $connectionName = $this->getTenantConnectionName($tenant);

        Config::offsetUnset("database.connections.{$connectionName}");

        DB::purge($connectionName);
    }

    public function switchToTenant(Tenant $tenant): void
    {
        $connectionName = $this->getTenantConnectionName($tenant);

        if (Config::get("database.connections.{$connectionName}") === null) {
            $this->createConnection($tenant);
        }

        Config::set('database.default', $connectionName);
    }

    public function restoreCentralConnection(): void
    {
        $centralName = $this->getCentralConnectionName();

        Config::set('database.default', $centralName);
    }

    public function isInstalled(Tenant $tenant): bool
    {
        $connectionName = $this->getTenantConnectionName($tenant);

        if (Config::get("database.connections.{$connectionName}") === null) {
            return false;
        }

        try {
            $pdo = $this->getPdo($tenant);

            if ($pdo === null) {
                return false;
            }

            $databaseName = $this->getTenantDatabaseName($tenant);

            $result = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
            $result->execute([$databaseName]);

            return $result->fetch() !== false;
        } catch (\Exception) {
            return false;
        }
    }

    public function getPdo(Tenant $tenant): ?\PDO
    {
        $connectionName = $this->getTenantConnectionName($tenant);

        if (Config::get("database.connections.{$connectionName}") === null) {
            return null;
        }

        try {
            return DB::connection($connectionName)->getPdo();
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    public function getTenantTables(\PDO $pdo): array
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $tables = $pdo->query(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
            )->fetchAll(\PDO::FETCH_COLUMN);
        } else {
            $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        }

        return $tables ?: [];
    }

    public function getCentralConnectionName(): string
    {
        return Config::get('database.connections.tenant_central.name', 'mysql');
    }

    public function getTenantConnectionName(Tenant $tenant): string
    {
        return "tenant_{$tenant->id}";
    }

    public function getTenantDatabaseName(Tenant $tenant): string
    {
        $prefix = Config::get('tenancy.database.prefix', 'tenant_');
        $suffix = Config::get('tenancy.database.suffix', '');

        return $prefix.$tenant->id.$suffix;
    }
}
