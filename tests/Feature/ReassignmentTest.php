<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Core\Models\Team;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Pipeline\Models\Customer;
use Deally\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class ReassignmentTest extends TestCase
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

    public function test_removing_a_sales_agent_reassigns_their_customers_to_a_teammate(): void
    {
        $alice = $this->user('alice@example.com');
        $charlie = $this->user('charlie@example.com');

        // Dana joins the same team (East Pod) as a fresh sales agent.
        $dana = $this->joinTeamAsSalesAgent('Dana Smith', 'dana@example.com', 'East Pod');

        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();

        $this->assertSame((string) $charlie->id, (string) $stark->owner_user_id);

        $membership = $charlie->getMembershipForTenant($this->acme());

        // The deactivation screen renders the plan with Dana suggested.
        $this->actingAs($alice)
            ->get(route('deally.users.deactivate', $membership))
            ->assertOk()
            ->assertSee('Stark Industries')
            ->assertSee('Dana Smith');

        $this->actingAs($alice)
            ->post(route('deally.users.reassignment.approve', $membership))
            ->assertSessionHas('toast');

        $this->assertSame((string) $dana->id, (string) $stark->fresh()->owner_user_id);
        $this->assertSame((string) $dana->id, (string) $stark->fresh()->opportunities()->first()->customer->owner_user_id);

        // The customer follows its new owner's team.
        $eastPod = Team::query()->forTenant($this->acme()->id)->where('name', 'East Pod')->firstOrFail();
        $this->assertSame((int) $eastPod->id, (int) $stark->fresh()->team_id);

        // Charlie's open task was on Acme Corp's account (owned by Alice), so
        // it follows that account's owner rather than Stark's successor.
        $task = Task::query()->where('linked_company', 'Acme Corp')->where('title', 'Send spec sheet to Acme')->firstOrFail();
        $this->assertSame((string) $alice->id, (string) $task->fresh()->owner_user_id);

        $this->assertDatabaseMissing('memberships', ['id' => $membership]);
        $this->assertSame(0, DB::table('team_user')->where('user_id', $charlie->id)->where('team_id', $eastPod->id)->count());
    }

    public function test_reassignment_picks_the_least_loaded_teammate(): void
    {
        $alice = $this->user('alice@example.com');
        $charlie = $this->user('charlie@example.com');

        // Two teammates: Frank is less loaded than Dana.
        $frank = $this->joinTeamAsSalesAgent('Frank Lloyd', 'frank@example.com', 'East Pod');
        $dana = $this->joinTeamAsSalesAgent('Dana Smith', 'dana@example.com', 'East Pod');

        Customer::query()->create(['company' => 'Dana One', 'owner_user_id' => $dana->id]);
        Customer::query()->create(['company' => 'Dana Two', 'owner_user_id' => $dana->id]);
        Customer::query()->create(['company' => 'Frank One', 'owner_user_id' => $frank->id]);

        $membership = $charlie->getMembershipForTenant($this->acme());

        $this->actingAs($alice)
            ->post(route('deally.users.reassignment.approve', $membership))
            ->assertSessionHas('toast');

        $this->assertSame(
            (string) $frank->id,
            (string) Customer::query()->where('company', 'Stark Industries')->first()->owner_user_id
        );
    }

    public function test_owner_override_takes_precedence_over_the_suggestion(): void
    {
        $alice = $this->user('alice@example.com');
        $charlie = $this->user('charlie@example.com');
        $dana = $this->joinTeamAsSalesAgent('Dana Smith', 'dana@example.com', 'East Pod');

        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();
        $membership = $charlie->getMembershipForTenant($this->acme());

        $this->actingAs($alice)
            ->post(route('deally.users.reassignment.owner', $membership), [
                'customer_id' => $stark->getKey(),
                'owner_user_id' => $dana->id,
            ])
            ->assertSessionHas('toast');

        $this->actingAs($alice)
            ->post(route('deally.users.reassignment.approve', $membership))
            ->assertSessionHas('toast');

        $this->assertSame((string) $dana->id, (string) $stark->fresh()->owner_user_id);
    }

    public function test_assign_selected_assigns_multiple_customers_to_one_agent(): void
    {
        $alice = $this->user('alice@example.com');
        $charlie = $this->user('charlie@example.com');
        $dana = $this->joinTeamAsSalesAgent('Dana Smith', 'dana@example.com', 'East Pod');

        // Charlie owns Stark in the seed; add a second customer for him.
        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();
        $starkTwo = Customer::query()->create([
            'company' => 'Stark Logistics',
            'owner_user_id' => $charlie->id,
        ]);

        $this->assertSame((string) $charlie->id, (string) $stark->owner_user_id);
        $this->assertSame((string) $charlie->id, (string) $starkTwo->owner_user_id);

        $membership = $charlie->getMembershipForTenant($this->acme());

        $this->actingAs($alice)
            ->post(route('deally.users.reassignment.assign-selected', $membership), [
                'customer_ids' => [$stark->getKey(), $starkTwo->getKey()],
                'owner_user_id' => $dana->id,
            ])
            ->assertSessionHas('toast');

        $this->actingAs($alice)
            ->post(route('deally.users.reassignment.approve', $membership))
            ->assertSessionHas('toast');

        $this->assertSame((string) $dana->id, (string) $stark->fresh()->owner_user_id);
        $this->assertSame((string) $dana->id, (string) $starkTwo->fresh()->owner_user_id);
    }

    public function test_destroy_redirects_a_sales_agent_to_the_plan(): void
    {
        $alice = $this->user('alice@example.com');
        $charlie = $this->user('charlie@example.com');

        $membership = $charlie->getMembershipForTenant($this->acme());

        $this->actingAs($alice)
            ->delete(route('deally.users.destroy', $membership))
            ->assertRedirect(route('deally.users.deactivate', $membership))
            ->assertSessionHas('toast');

        // Nothing was removed yet — the member must approve the plan first.
        $this->assertDatabaseHas('memberships', ['id' => $membership->id]);
    }

    public function test_removing_a_team_leader_does_not_cascade_ownership(): void
    {
        $alice = $this->user('alice@example.com');
        $erica = $this->user('erica@example.com');

        $customer = Customer::query()->create([
            'company' => 'Erica Co',
            'owner_user_id' => $erica->id,
        ]);

        $membership = $erica->getMembershipForTenant($this->acme());
        $this->assertTrue($membership->hasRole('team-leader'));

        $this->actingAs($alice)
            ->get(route('deally.users.deactivate', $membership))
            ->assertOk()
            ->assertSee('No reassignment plan');

        $this->actingAs($alice)
            ->delete(route('deally.users.confirm-removal', $membership))
            ->assertSessionHas('toast');

        // Seat departures are filled by the tenant admin, never cascaded.
        $this->assertSame((string) $erica->id, (string) $customer->fresh()->owner_user_id);
        $this->assertDatabaseMissing('memberships', ['id' => $membership->id]);
    }

    private function acme(): Tenant
    {
        return Tenant::query()->where('slug', 'acme-corp')->firstOrFail();
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function joinTeamAsSalesAgent(string $name, string $email, string $teamName): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);

        $membership = Membership::query()->create([
            'user_id' => $user->id,
            'tenant_id' => $this->acme()->id,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        $salesAgent = Role::query()->whereNull('tenant_id')->where('slug', 'sales-agent')->firstOrFail();

        $membership->roles()->attach($salesAgent->id);

        $team = Team::query()->forTenant($this->acme()->id)->where('name', $teamName)->firstOrFail();

        $team->members()->attach($user->id);

        return $user;
    }
}
