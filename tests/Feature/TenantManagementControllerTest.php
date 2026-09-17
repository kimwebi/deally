<?php

namespace Tests\Feature;

use Deally\Core\Models\User;
use Deally\TenantManagement\Contracts\TenantInstanceManager;
use Deally\TenantManagement\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantManagementControllerTest extends TestCase
{
    use RefreshDatabase;

    private FakeTenantInstanceManager $tenantInstanceManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantInstanceManager = new FakeTenantInstanceManager;
        $this->app->instance(TenantInstanceManager::class, $this->tenantInstanceManager);
    }

    public function test_superadmin_sees_all_tenants(): void
    {
        $adminA = $this->makeUser(isAdmin: true);
        $adminB = $this->makeUser(isAdmin: true);
        $superadmin = $this->makeUser(isSuperadmin: true);

        $this->makeTenant($adminA);
        $this->makeTenant($adminA);
        $this->makeTenant($adminB);

        $this->actingAs($superadmin)
            ->getJson('/admin/tenants')
            ->assertOk()
            ->assertJsonCount(3);
    }

    public function test_admin_only_sees_own_tenants(): void
    {
        $adminA = $this->makeUser(isAdmin: true);
        $adminB = $this->makeUser(isAdmin: true);

        $this->makeTenant($adminA);
        $this->makeTenant($adminA);
        $this->makeTenant($adminB);

        $this->actingAs($adminA)
            ->getJson('/admin/tenants')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_regular_user_cannot_view_tenants(): void
    {
        $this->actingAs($this->makeUser())
            ->getJson('/admin/tenants')
            ->assertForbidden();
    }

    public function test_admin_can_create_tenant(): void
    {
        $admin = $this->makeUser(isAdmin: true);

        $response = $this->actingAs($admin)
            ->postJson('/admin/tenants', ['name' => 'Acme Corp'])
            ->assertCreated();

        $response->assertJsonPath('db_name', 'acme-corp');
        $this->assertDatabaseHas('tenants', [
            'name' => 'Acme Corp',
            'db_name' => 'acme-corp',
            'user_id' => $admin->id,
        ]);

        $this->assertSame([['Acme Corp', 'acme-corp']], $this->tenantInstanceManager->created);
    }

    public function test_regular_user_cannot_create_tenant(): void
    {
        $this->actingAs($this->makeUser())
            ->postJson('/admin/tenants', ['name' => 'Acme Corp'])
            ->assertForbidden();

        $this->assertDatabaseMissing('tenants', ['name' => 'Acme Corp']);
    }

    public function test_superadmin_can_clone_tenant(): void
    {
        $admin = $this->makeUser(isAdmin: true);
        $tenant = $this->makeTenant($admin);

        $this->actingAs($this->makeUser(isSuperadmin: true))
            ->postJson("/admin/tenants/{$tenant->id}/clone")
            ->assertOk()
            ->assertJsonPath('db_name', $tenant->db_name.'_backup');

        $this->assertSame(
            [[$tenant->db_name, $tenant->db_name.'_backup']],
            $this->tenantInstanceManager->cloned
        );
    }

    public function test_admin_cannot_clone_tenant(): void
    {
        $admin = $this->makeUser(isAdmin: true);
        $tenant = $this->makeTenant($admin);

        $this->actingAs($admin)
            ->postJson("/admin/tenants/{$tenant->id}/clone")
            ->assertForbidden();

        $this->assertSame([], $this->tenantInstanceManager->cloned);
    }

    private function makeUser(bool $isAdmin = false, bool $isSuperadmin = false): User
    {
        return User::unguarded(fn () => User::create([
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'is_active' => true,
            'is_admin' => $isAdmin,
            'is_superadmin' => $isSuperadmin,
        ]));
    }

    private function makeTenant(User $user): Tenant
    {
        $base = 'acme-corp-'.(string) Str::lower(Str::random(6));

        return Tenant::create([
            'name' => 'Acme Corp',
            'slug' => $base,
            'db_name' => $base,
            'user_id' => $user->id,
        ]);
    }
}

class FakeTenantInstanceManager implements TenantInstanceManager
{
    /** @var array<int, array{0: string, 1: string}> */
    public array $created = [];

    /** @var array<int, array{0: string, 1: string}> */
    public array $cloned = [];

    public function createInstance(string $name, string $dbName): void
    {
        $this->created[] = [$name, $dbName];
    }

    public function cloneInstance(string $sourceDbName, string $cloneDbName): void
    {
        $this->cloned[] = [$sourceDbName, $cloneDbName];
    }
}
