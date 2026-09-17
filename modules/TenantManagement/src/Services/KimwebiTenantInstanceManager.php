<?php

namespace Deally\TenantManagement\Services;

use Deally\TenantManagement\Contracts\TenantInstanceManager;
use Kimwebi\MultitenantManager\Facades\TenantManager;

class KimwebiTenantInstanceManager implements TenantInstanceManager
{
    public function createInstance(string $name, string $dbName): void
    {
        TenantManager::createInstance([
            'name' => $name,
            'db_name' => $dbName,
        ]);
    }

    public function cloneInstance(string $sourceDbName, string $cloneDbName): void
    {
        TenantManager::cloneInstance($sourceDbName, $cloneDbName);
    }
}
