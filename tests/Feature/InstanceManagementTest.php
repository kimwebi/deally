<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class InstanceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([DemoSeeder::class, DeallyAccessSeeder::class]);

        $provisioner = app(DeallyTenantProvisioner::class);

        foreach (Tenant::query()->active()->get() as $tenant) {
            $provisioner->migrate($tenant);
            $provisioner->seed($tenant);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob(database_path('tenants').DIRECTORY_SEPARATOR.'*.sqlite') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_member_can_switch_to_another_instance_and_workspace_follows(): void
    {
        $david = User::query()->where('email', 'david@example.com')->firstOrFail();
        $acme = Tenant::query()->where('slug', 'acme-corp')->firstOrFail();
        $globex = Tenant::query()->where('slug', 'globex')->firstOrFail();

        $this->actingAs($david)
            ->withSession(['tenant_id' => $acme->id])
            ->get(route('deally.workspace'))
            ->assertOk()
            ->assertSee('/app/admin/users')
            ->assertSee('Instances');

        $this->actingAs($david)
            ->post(route('tenant.switch', $globex))
            ->assertRedirect(route('deally.workspace'))
            ->assertSessionHas('tenant_id', $globex->id);

        $this->actingAs($david)
            ->withSession(['tenant_id' => $globex->id])
            ->get(route('deally.workspace'))
            ->assertOk()
            ->assertSee('Globex')
            ->assertDontSee('/app/admin/users')
            ->assertDontSee('/app/admin/roles')
            ->assertSee('Instances');
    }

    public function test_member_can_switch_back_to_a_previously_owned_instance(): void
    {
        $alice = User::query()->where('email', 'alice@example.com')->firstOrFail();
        $acme = Tenant::query()->where('slug', 'acme-corp')->firstOrFail();
        $globex = Tenant::query()->where('slug', 'globex')->firstOrFail();

        $this->actingAs($alice)->post(route('tenant.switch', $acme))
            ->assertRedirect(route('deally.workspace'))
            ->assertSessionHas('tenant_id', $acme->id);

        $this->actingAs($alice)
            ->withSession(['tenant_id' => $acme->id])
            ->get(route('deally.workspace'))
            ->assertOk()
            ->assertSee('Acme Corp')
            ->assertSee('/app/admin/users');
    }

    public function test_switching_to_a_tenant_without_membership_is_forbidden(): void
    {
        $erica = User::query()->where('email', 'erica@example.com')->firstOrFail();
        $globex = Tenant::query()->where('slug', 'globex')->firstOrFail();

        $this->actingAs($erica)
            ->post(route('tenant.switch', $globex))
            ->assertForbidden();
    }

    public function test_superadmin_with_session_tenant_renders_workspace_without_membership(): void
    {
        $superadmin = User::unguarded(fn () => User::create([
            'name' => 'Super Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => true,
        ]));

        $acme = Tenant::query()->where('slug', 'acme-corp')->firstOrFail();

        $this->actingAs($superadmin)
            ->withSession(['tenant_id' => $acme->id])
            ->get(route('deally.workspace'))
            ->assertOk()
            ->assertSee('Acme Corp')
            ->assertSee('Instances');
    }

    public function test_sidebar_footer_shows_primary_role_not_company_name(): void
    {
        $alice = User::query()->where('email', 'alice@example.com')->firstOrFail();
        $david = User::query()->where('email', 'david@example.com')->firstOrFail();
        $charlie = User::query()->where('email', 'charlie@example.com')->firstOrFail();
        $acme = Tenant::query()->where('slug', 'acme-corp')->firstOrFail();
        $globex = Tenant::query()->where('slug', 'globex')->firstOrFail();

        $this->actingAs($alice)
            ->withSession(['tenant_id' => $acme->id])
            ->get(route('deally.workspace'))
            ->assertOk()
            ->assertSee('Tenant Owner');

        $this->actingAs($david)
            ->withSession(['tenant_id' => $globex->id])
            ->get(route('deally.workspace'))
            ->assertOk()
            ->assertSee('Solutions Lead');

        $this->actingAs($charlie)
            ->withSession(['tenant_id' => $acme->id])
            ->get(route('deally.workspace'))
            ->assertOk()
            ->assertSee('Sales Agent');
    }

    public function test_sidebar_footer_shows_active_instance_with_green_badge(): void
    {
        $david = User::query()->where('email', 'david@example.com')->firstOrFail();
        $globex = Tenant::query()->where('slug', 'globex')->firstOrFail();

        $this->actingAs($david)
            ->withSession(['tenant_id' => $globex->id])
            ->get(route('deally.workspace'))
            ->assertOk()
            ->assertSee('instance-logo')
            ->assertSee('instance-badge-dot')
            ->assertSee('Globex');
    }

    public function test_owner_limit_blocks_granting_an_extra_instance(): void
    {
        config(['saas.instances.max_per_owner' => 1]);

        $alice = User::query()->where('email', 'alice@example.com')->firstOrFail();
        $acme = Tenant::query()->where('slug', 'acme-corp')->firstOrFail();
        $globex = Tenant::query()->where('slug', 'globex')->firstOrFail();

        $dana = $this->memberOwning($globex);
        $ownerRole = $this->ownerRole();

        $this->actingAs($alice)
            ->withSession(['tenant_id' => $acme->id])
            ->post(route('deally.users.store'), [
                'email' => $dana->email,
                'role_ids' => [$ownerRole->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertDatabaseMissing('memberships', ['user_id' => $dana->id, 'tenant_id' => $acme->id]);
    }

    public function test_owner_limit_allows_new_instance_under_the_cap(): void
    {
        config(['saas.instances.max_per_owner' => 2]);

        $alice = User::query()->where('email', 'alice@example.com')->firstOrFail();
        $acme = Tenant::query()->where('slug', 'acme-corp')->firstOrFail();
        $globex = Tenant::query()->where('slug', 'globex')->firstOrFail();

        $dana = $this->memberOwning($globex);
        $ownerRole = $this->ownerRole();

        $this->actingAs($alice)
            ->withSession(['tenant_id' => $acme->id])
            ->post(route('deally.users.store'), [
                'email' => $dana->email,
                'role_ids' => [$ownerRole->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $membership = Membership::query()
            ->where('user_id', $dana->id)
            ->where('tenant_id', $acme->id)
            ->firstOrFail();

        $this->assertTrue($membership->hasRole('owner'));
    }

    public function test_owner_limit_blocks_role_update_to_owner_past_the_cap(): void
    {
        config(['saas.instances.max_per_owner' => 1]);

        $alice = User::query()->where('email', 'alice@example.com')->firstOrFail();
        $acme = Tenant::query()->where('slug', 'acme-corp')->firstOrFail();
        $globex = Tenant::query()->where('slug', 'globex')->firstOrFail();

        $dana = $this->memberOwning($globex);
        $ownerRole = $this->ownerRole();

        $membership = Membership::query()->firstOrCreate(
            ['user_id' => $dana->id, 'tenant_id' => $acme->id],
            ['status' => Membership::STATUS_ACTIVE, 'joined_at' => now()]
        );

        $agentRole = Role::query()->whereNull('tenant_id')->where('slug', 'sales-agent')->firstOrFail();
        $membership->roles()->sync([$agentRole->id]);

        $this->actingAs($alice)
            ->withSession(['tenant_id' => $acme->id])
            ->put(route('deally.users.update', $membership), [
                'role_ids' => [$ownerRole->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertFalse($membership->refresh()->hasRole('owner'));
    }

    private function memberOwning(Tenant $tenant): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'dana@example.com'],
            ['name' => 'Dana Smith', 'password' => 'password', 'is_active' => true]
        );

        $membership = Membership::query()->firstOrCreate(
            ['user_id' => $user->id, 'tenant_id' => $tenant->id],
            ['status' => Membership::STATUS_ACTIVE, 'joined_at' => now()]
        );

        $membership->roles()->syncWithoutDetaching($this->ownerRole()->id);

        return $user;
    }

    private function ownerRole(): Role
    {
        return Role::query()->whereNull('tenant_id')->where('slug', 'owner')->firstOrFail();
    }
}
