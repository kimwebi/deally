<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        $connectionNames = array_keys(Config::get('database.connections') ?? []);

        foreach ($connectionNames as $name) {
            if (str_starts_with($name, 'tenant_') || $name === 'deally') {
                DB::purge($name);
            }
        }

        foreach (glob(database_path('tenants').DIRECTORY_SEPARATOR.'*.sqlite') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }
}
