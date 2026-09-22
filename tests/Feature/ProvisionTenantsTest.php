<?php

namespace Tests\Feature;

use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class ProvisionTenantsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (['tenant_1', 'tenant_2', 'deally'] as $connection) {
            DB::disconnect($connection);
        }

        foreach (glob(database_path('tenants').DIRECTORY_SEPARATOR.'*.sqlite') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_command_seeds_central_demo_data_and_provisions_tenant_databases(): void
    {
        $this->artisan('deally:tenants:setup')
            ->expectsOutputToContain('Seeding central demo data')
            ->expectsOutputToContain('ready')
            ->assertExitCode(0);

        $this->assertSame(2, Tenant::query()->active()->count());

        $alice = User::query()->where('email', 'alice@example.com')->firstOrFail();
        $globex = Tenant::query()->where('slug', 'globex')->firstOrFail();
        $bob = User::query()->where('email', 'bob@example.com')->firstOrFail();

        $this->assertTrue($alice->getMembershipForTenant($globex)->hasRole('owner'));
        $this->assertTrue($bob->getMembershipForTenant($globex)->hasRole('admin'));

        $manager = app(DeallyTenantDatabaseManager::class);
        $connection = $manager->getTenantConnectionName($globex);

        $this->assertTrue(DB::connection($connection)->getDatabaseName() !== '');
        $this->assertGreaterThan(0, DB::connection($connection)->table('tasks')->count());
    }
}
