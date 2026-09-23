<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaasFoundation\Models\Permission;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
        $this->seed(DeallyAccessSeeder::class);

        $acme = Tenant::query()->where('slug', 'acme-corp')->firstOrFail();
        $provisioner = app(DeallyTenantProvisioner::class);
        $provisioner->migrate($acme);
        $provisioner->seed($acme);
    }

    protected function tearDown(): void
    {
        foreach (glob(database_path('tenants').DIRECTORY_SEPARATOR.'*.sqlite') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_owner_can_view_roles_index(): void
    {
        $this->acme()->roles()->create(['name' => 'Ops Manager']);

        $this->actingAs($this->user('alice@example.com'))
            ->get(route('deally.roles.index'))
            ->assertOk()
            ->assertSee('Roles')
            ->assertSee('Ops Manager');
    }

    public function test_roles_index_lists_roles_used_in_this_instance_with_scope(): void
    {
        $this->acme()->roles()->create(['name' => 'Ops Manager']);

        $this->actingAs($this->user('alice@example.com'))
            ->get(route('deally.roles.index'))
            ->assertOk()
            ->assertSee('Global')
            ->assertSee('This instance')
            ->assertSee('Tenant Owner')
            ->assertSee('Viewer')
            ->assertSee('Sales Agent')
            ->assertSee('Team Leader')
            ->assertSee('Administrator')
            ->assertSee('Platform Support')
            ->assertDontSee('Tenant Administrator')
            ->assertSee('Ops Manager')
            ->assertSee('7 roles')
            ->assertDontSee('Super Administrator');
    }

    public function test_owner_can_update_global_system_role_permissions(): void
    {
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'owner')->firstOrFail();

        $usersView = Permission::query()->where('slug', 'users.view')->firstOrFail();
        $billingManage = Permission::query()->where('slug', 'billing.manage')->firstOrFail();
        $role->permissions()->sync($usersView->id);

        $this->actingAs($this->user('alice@example.com'))
            ->put(route('deally.roles.update', $role), [
                'name' => 'Member',
                'permissions' => [$usersView->id, $billingManage->id],
            ])
            ->assertRedirect(route('deally.roles.index'));

        $role->refresh();

        $this->assertTrue($role->permissions()->where('id', $billingManage->id)->exists());
        $this->assertTrue($role->permissions()->where('id', $usersView->id)->exists());
    }

    public function test_viewer_cannot_manage_roles(): void
    {
        $this->actingAs($this->user('charlie@example.com'))
            ->get(route('deally.roles.index'))
            ->assertForbidden();
    }

    public function test_owner_can_create_role_with_permissions(): void
    {
        $permission = Permission::query()->where('slug', 'users.view')->firstOrFail();

        $this->actingAs($this->user('alice@example.com'))
            ->post(route('deally.roles.store'), [
                'name' => 'Sales Manager',
                'description' => 'Manages the pipeline',
                'permissions' => [$permission->id],
            ])
            ->assertRedirect(route('deally.roles.index'));

        $role = Role::query()->where('name', 'Sales Manager')->firstOrFail();

        $this->assertSame($this->acme()->id, $role->tenant_id);
        $this->assertTrue($role->permissions()->where('id', $permission->id)->exists());
    }

    public function test_owner_can_update_role_permissions(): void
    {
        $role = $this->acme()->roles()->create(['name' => 'Manager']);
        $usersView = Permission::query()->where('slug', 'users.view')->firstOrFail();
        $projectsDelete = Permission::query()->where('slug', 'projects.delete')->firstOrFail();
        $role->permissions()->sync($usersView->id);

        $this->actingAs($this->user('alice@example.com'))
            ->put(route('deally.roles.update', $role), [
                'name' => 'Manager',
                'permissions' => [$projectsDelete->id],
            ])
            ->assertRedirect(route('deally.roles.index'));

        $this->assertFalse($role->refresh()->permissions()->where('id', $usersView->id)->exists());
        $this->assertTrue($role->permissions()->where('id', $projectsDelete->id)->exists());
    }

    public function test_role_from_another_tenant_cannot_be_modified(): void
    {
        $globex = Tenant::query()->where('slug', 'globex')->firstOrFail();
        $role = $globex->roles()->create(['name' => 'Globex Only']);

        $this->actingAs($this->user('alice@example.com'))
            ->put(route('deally.roles.update', $role), ['name' => 'Hacked'])
            ->assertNotFound();
    }

    public function test_non_admin_cannot_create_roles(): void
    {
        $this->actingAs($this->user('charlie@example.com'))
            ->post(route('deally.roles.store'), ['name' => 'Nope'])
            ->assertForbidden();
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        $systemRole = Role::query()->where('slug', 'admin')->firstOrFail();

        $this->actingAs($this->user('alice@example.com'))
            ->delete(route('deally.roles.destroy', $systemRole))
            ->assertNotFound();

        $this->assertDatabaseHas('roles', ['slug' => 'admin']);
    }

    private function acme(): Tenant
    {
        return Tenant::query()->where('slug', 'acme-corp')->firstOrFail();
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
