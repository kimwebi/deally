<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Core\Models\Team;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Pipeline\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Permission;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class CustomersPageTest extends TestCase
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

    public function test_customers_permissions_are_seeded_onto_the_right_roles(): void
    {
        $this->assertTrue(Permission::query()->where('slug', 'deally.customers.view')->exists());
        $this->assertTrue(Permission::query()->where('slug', 'deally.customers.manage')->exists());

        $agent = Role::query()->whereNull('tenant_id')->where('slug', 'sales-agent')->firstOrFail();
        $this->assertTrue($agent->permissions()->where('slug', 'deally.customers.view')->exists());
        $this->assertTrue($agent->permissions()->where('slug', 'deally.customers.manage')->exists());

        $viewer = Role::query()->whereNull('tenant_id')->where('slug', 'viewer')->firstOrFail();
        $this->assertTrue($viewer->permissions()->where('slug', 'deally.customers.view')->exists());
        $this->assertFalse($viewer->permissions()->where('slug', 'deally.customers.manage')->exists());
    }

    public function test_sales_agent_sees_only_customers_they_own(): void
    {
        $this->actingAs($this->user('charlie@example.com'))
            ->get(route('deally.customers.index'))
            ->assertOk()
            ->assertSee('Stark Industries')
            ->assertDontSee('Wayne Enterprises');
    }

    public function test_team_leader_sees_their_teams_customers(): void
    {
        $erica = $this->user('erica@example.com');
        $bob = $this->user('bob@example.com');

        // Bob belongs to the Core Pod in Globex, not the East Pod in Acme —
        // his account must stay out of Erica's reach.
        Customer::query()->create([
            'company' => 'Hidden Co',
            'owner_user_id' => $bob->id,
        ]);

        $this->actingAs($erica)
            ->get(route('deally.customers.index'))
            ->assertOk()
            ->assertSee('Wayne Enterprises')
            ->assertSee('Stark Industries')
            ->assertDontSee('Hidden Co');
    }

    public function test_admin_sees_every_customer_in_the_instance(): void
    {
        $this->actingAs($this->user('bob@example.com'))
            ->get(route('deally.customers.index'))
            ->assertOk()
            ->assertSee('Wayne Enterprises')
            ->assertSee('Stark Industries')
            ->assertSee('Alice Johnson')
            ->assertSee('Charlie Lee');
    }

    public function test_viewer_can_browse_customers_without_managing_them(): void
    {
        $this->actingAs($this->user('support@example.com'))
            ->get(route('deally.customers.index'))
            ->assertOk()
            ->assertSee('Wayne Enterprises')
            ->assertDontSee('Add Customer');
    }

    public function test_detail_page_is_scoped_to_the_seat(): void
    {
        $acme = Customer::query()->where('company', 'Acme Corp')->firstOrFail();
        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();

        $this->actingAs($this->user('bob@example.com'))
            ->get(route('deally.customers.show', $acme))
            ->assertOk();

        $this->actingAs($this->user('charlie@example.com'))
            ->get(route('deally.customers.show', $stark))
            ->assertOk()
            ->assertSee('Stark Industries');

        $this->actingAs($this->user('charlie@example.com'))
            ->get(route('deally.customers.show', $acme))
            ->assertForbidden();
    }

    public function test_user_without_view_permission_is_forbidden(): void
    {
        $nobody = User::factory()->create(['name' => 'Nobody Special', 'password' => 'password']);

        $membership = Membership::query()->create([
            'user_id' => $nobody->id,
            'tenant_id' => $this->acme()->id,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        $bare = Role::query()->create([
            'tenant_id' => null,
            'slug' => 'bare-seat',
            'name' => 'Bare Seat',
            'description' => 'A seat with no DeAlly permissions.',
            'is_system' => false,
        ]);

        $membership->roles()->attach($bare);

        $this->actingAs($nobody)
            ->get(route('deally.customers.index'))
            ->assertForbidden();
    }

    public function test_agent_adds_a_customer_they_own(): void
    {
        $charlie = $this->user('charlie@example.com');

        $this->actingAs($charlie)
            ->post(route('deally.customers.store'), [
                'company' => 'Umbrella Corp',
                'contact_name' => 'Ada',
                'contact_title' => 'COO',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $customer = Customer::query()->where('company', 'Umbrella Corp')->firstOrFail();

        $this->assertSame((string) $charlie->id, (string) $customer->owner_user_id);

        $eastPod = Team::query()->where('tenant_id', $this->acme()->id)->where('name', 'East Pod')->firstOrFail();
        $this->assertSame((string) $eastPod->getKey(), (string) $customer->team_id);
    }

    public function test_agent_can_hand_an_account_off_to_a_teammate(): void
    {
        $charlie = $this->user('charlie@example.com');
        $erica = $this->user('erica@example.com');
        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();

        $this->actingAs($charlie)
            ->patch(route('deally.customers.update', $stark), [
                'owner_user_id' => $erica->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $stark->refresh();

        $this->assertSame((string) $erica->id, (string) $stark->owner_user_id);
        $this->assertSame(
            (string) Team::query()->where('tenant_id', $this->acme()->id)->where('name', 'East Pod')->firstOrFail()->getKey(),
            (string) $stark->team_id
        );
    }

    public function test_agent_cannot_reassign_outside_their_team(): void
    {
        $charlie = $this->user('charlie@example.com');
        $bob = $this->user('bob@example.com');
        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();

        $this->actingAs($charlie)
            ->patch(route('deally.customers.update', $stark), [
                'owner_user_id' => $bob->id,
            ])
            ->assertSessionHasErrors('owner_user_id');

        $this->assertSame((string) $charlie->id, (string) $stark->fresh()->owner_user_id);
    }

    public function test_admin_reassigns_a_customer_and_the_team_follows(): void
    {
        $bob = $this->user('bob@example.com');
        $alice = $this->user('alice@example.com');
        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();

        $this->actingAs($bob)
            ->patch(route('deally.customers.update', $stark), [
                'owner_user_id' => $alice->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $stark->refresh();

        $this->assertSame((string) $alice->id, (string) $stark->owner_user_id);

        $this->actingAs($this->user('charlie@example.com'))
            ->get(route('deally.customers.index'))
            ->assertOk()
            ->assertDontSee('Stark Industries');
    }

    public function test_agent_cannot_edit_a_customer_they_do_not_own(): void
    {
        $acme = Customer::query()->where('company', 'Acme Corp')->firstOrFail();

        $this->actingAs($this->user('charlie@example.com'))
            ->patch(route('deally.customers.update', $acme), [
                'contact_name' => 'Hacked',
            ])
            ->assertForbidden();
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
