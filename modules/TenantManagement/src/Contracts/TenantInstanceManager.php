<?php

namespace Deally\TenantManagement\Contracts;

interface TenantInstanceManager
{
    public function createInstance(string $name, string $dbName): void;

    public function cloneInstance(string $sourceDbName, string $cloneDbName): void;
}
