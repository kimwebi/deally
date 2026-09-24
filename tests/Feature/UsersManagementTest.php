<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class UsersManagementTest extends TestCase
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
        foreach (['tenant_1', 'tenant_2', 'deally'] as $connection) {
            DB::disconnect($connection);
        }

        foreach (glob(database_path('tenants').DIRECTORY_SEPARATOR.'*.sqlite') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_owner_can_add_a_new_user_to_the_tenant_with_a_role(): void
    {
        $alice = User::query()->where('email', 'alice@example.com')->firstOrFail();
        $salesAgent = Role::query()->whereNull('tenant_id')->where('slug', 'sales-agent')->firstOrFail();

        $this->actingAs($alice)
            ->post(route('deally.users.store'), [
                'name' => 'Dana Smith',
                'email' => 'dana@example.com',
                'password' => 'secret-password',
                'role_ids' => [$salesAgent->id],
            ])
            ->assertRedirect();

        $user = User::query()->where('email', 'dana@example.com')->firstOrFail();
        $membership = Membership::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $this->acme()->id)
            ->firstOrFail();

        $this->assertSame('active', $membership->status);
        $this->assertTrue($membership->hasRole('sales-agent'));
    }

    public function test_adding_an_existing_user_attaches_a_membership_without_new_account(): void
    {
        $alice = User::query()->where('email', 'alice@example.com')->firstOrFail();
        $charlie = User::query()->where('email', 'charlie@example.com')->firstOrFail();
        $teamLeader = Role::query()->whereNull('tenant_id')->where('slug', 'team-leader')->firstOrFail();

        $this->actingAs($alice)
            ->post(route('deally.users.store'), [
                'name' => 'Charlie Lee',
                'email' => 'charlie@example.com',
                'role_ids' => [$teamLeader->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $charlie->refresh();

        $this->assertTrue($charlie->belongsToTenant($this->acme()));
        $membership = $charlie->getMembershipForTenant($this->acme());
        $this->assertNotNull($membership);
        $this->assertTrue($membership->hasRole('team-leader'));
        $this->assertSame(1, User::query()->where('email', 'charlie@example.com')->count());
    }

    public function test_creating_a_new_user_requires_a_password(): void
    {
        $alice = User::query()->where('email', 'alice@example.com')->firstOrFail();
        $salesAgent = Role::query()->whereNull('tenant_id')->where('slug', 'sales-agent')->firstOrFail();

        $this->actingAs($alice)
            ->post(route('deally.users.store'), [
                'name' => 'No Pass',
                'email' => 'nopass@example.com',
                'role_ids' => [$salesAgent->id],
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'nopass@example.com']);
    }

    public function test_member_roles_can_be_updated(): void
    {
        $alice = User::query()->where('email', 'alice@example.com')->firstOrFail();
        $charlie = User::query()->where('email', 'charlie@example.com')->firstOrFail();
        $teamLeader = Role::query()->whereNull('tenant_id')->where('slug', 'team-leader')->firstOrFail();

        $membership = $charlie->getMembershipForTenant($this->acme());
        $this->assertNotNull($membership);
        $this->assertTrue($membership->hasRole('sales-agent'));

        $this->actingAs($alice)
            ->put(route('deally.users.update', $membership), [
                'role_ids' => [$teamLeader->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $membership->refresh();

        $this->assertTrue($membership->hasRole('team-leader'));
        $this->assertFalse($membership->hasRole('sales-agent'));
    }

    public function test_a_member_can_be_removed_from_the_tenant(): void
    {
        $alice = User::query()->where('email', 'alice@example.com')->firstOrFail();
        $support = User::query()->where('email', 'support@example.com')->firstOrFail();

        $membership = $support->getMembershipForTenant($this->acme());
        $this->assertNotNull($membership);

        $this->actingAs($alice)
            ->delete(route('deally.users.destroy', $membership))
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertDatabaseMissing('memberships', ['id' => $membership->id]);
    }

    public function test_you_cannot_remove_yourself_from_the_tenant(): void
    {
        $alice = User::query()->where('email', 'alice@example.com')->firstOrFail();

        $membership = $alice->getMembershipForTenant($this->acme());
        $this->assertNotNull($membership);

        $this->actingAs($alice)
            ->delete(route('deally.users.destroy', $membership))
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertDatabaseHas('memberships', ['id' => $membership->id]);
    }

    public function test_sales_agent_cannot_manage_users(): void
    {
        $charlie = User::query()->where('email', 'charlie@example.com')->firstOrFail();

        $this->actingAs($charlie)->get(route('deally.users.index'))->assertForbidden();

        $this->actingAs($charlie)
            ->post(route('deally.users.store'), [
                'name' => 'Nope',
                'email' => 'nope@example.com',
                'password' => 'secret-password',
                'role_ids' => [],
            ])
            ->assertForbidden();
    }

    public function test_members_of_another_tenant_cannot_be_managed(): void
    {
        $david = User::query()->where('email', 'david@example.com')->firstOrFail();
        $globex = Tenant::query()->where('slug', 'globex')->firstOrFail();

        $globexMembership = $david->getMembershipForTenant($globex);
        $this->assertNotNull($globexMembership);

        $acmeAdmin = User::query()->where('email', 'alice@example.com')->firstOrFail();

        $this->actingAs($acmeAdmin)
            ->put(route('deally.users.update', $globexMembership), ['role_ids' => []])
            ->assertNotFound();

        $this->assertDatabaseHas('memberships', ['id' => $globexMembership->id]);
    }

    private function acme(): Tenant
    {
        return Tenant::query()->where('slug', 'acme-corp')->firstOrFail();
    }
}
