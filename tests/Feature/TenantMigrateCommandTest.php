<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Core\Services\DeallyTenantDatabaseManager;
use Deally\Core\Services\DeallyTenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class TenantMigrateCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
        $this->seed(DeallyAccessSeeder::class);

        $this->tenant = Tenant::query()->where('slug', 'acme-corp')->firstOrFail();
        $this->manager = app(DeallyTenantDatabaseManager::class);

        $provisioner = app(DeallyTenantProvisioner::class);
        $provisioner->migrate($this->tenant);
        $provisioner->seed($this->tenant);
    }

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

    public function test_tenant_migrate_is_a_noop_when_everything_is_migrated(): void
    {
        $this->artisan('tenant:migrate', ['--tenant' => 'acme-corp'])
            ->assertSuccessful();

        $this->manager->createConnection($this->tenant);
        $connection = $this->manager->getTenantConnectionName($this->tenant);

        $this->assertTrue(Schema::connection($connection)->hasTable('opportunities'));
        $this->assertTrue(Schema::connection($connection)
            ->hasColumn('opportunities', 'owner_user_id'));
    }

    public function test_tenant_migrate_applies_only_pending_migrations(): void
    {
        $this->manager->createConnection($this->tenant);
        $connection = $this->manager->getTenantConnectionName($this->tenant);

        foreach ([
            'opportunities' => 'opportunities_owner_user_id_index',
            'calls' => 'calls_owner_user_id_index',
            'tasks' => 'tasks_owner_user_id_index',
            'proposals' => 'proposals_owner_user_id_index',
        ] as $table => $index) {
            DB::connection($connection)->statement("drop index if exists {$index}");
            DB::connection($connection)->statement("alter table {$table} drop column owner_user_id");
        }

        DB::connection($connection)->table('migrations')
            ->where('migration', '2026_09_22_000003_add_owner_user_id_to_tenant_records_table')
            ->delete();

        $this->artisan('tenant:migrate', ['--tenant' => 'acme-corp'])
            ->assertSuccessful();

        $this->assertTrue(Schema::connection($connection)
            ->hasColumn('opportunities', 'owner_user_id'));
        $this->assertTrue(Schema::connection($connection)
            ->hasColumn('calls', 'owner_user_id'));
        $this->assertTrue(Schema::connection($connection)
            ->hasTable('opportunities'));
    }
}
