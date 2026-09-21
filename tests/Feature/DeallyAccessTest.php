<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Core\Models\Team;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class DeallyAccessTest extends TestCase
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

    public function test_sales_agent_sees_only_their_own_records(): void
    {
        $this->actingAs($this->user('charlie@example.com'));

        $this->get(route('deally.pipeline'))
            ->assertOk()
            ->assertSee('Stark Industries')
            ->assertDontSee('Wayne Enterprises')
            ->assertDontSee('Globex Inc');

        $this->get(route('deally.tasks.index'))
            ->assertOk()
            ->assertSee('Send spec sheet to Acme')
            ->assertDontSee('Review Call — Acme Corp');

        $this->get(route('deally.calls.index'))
            ->assertOk()
            ->assertDontSee('Demo & Discovery');

        $this->get(route('deally.proposals.index'))
            ->assertOk()
            ->assertDontSee('Globex Pro Plan')
            ->assertDontSee('Initech Starter');
    }

    public function test_agent_can_use_seat_features_but_not_admin_areas(): void
    {
        $this->actingAs($this->user('charlie@example.com'));

        $this->get(route('deally.workspace'))->assertOk();
        $this->get(route('deally.kb.index'))->assertOk();
        $this->post(route('deally.tasks.store'), ['title' => 'Follow up Charlie'])->assertRedirect();

        $this->get(route('deally.reporting'))->assertForbidden();
        $this->get(route('deally.roles.index'))->assertForbidden();
        $this->get(route('deally.teams.index'))->assertForbidden();
        $this->get(route('deally.activity.index'))->assertForbidden();
    }

    public function test_owner_sees_every_record(): void
    {
        $this->actingAs($this->user('alice@example.com'));

        $this->get(route('deally.tasks.index'))
            ->assertOk()
            ->assertSee('Review Call — Acme Corp')
            ->assertSee('Prep battle card for Acme');
    }

    public function test_team_leader_sees_team_members_records(): void
    {
        $charlie = $this->user('charlie@example.com');
        $alice = $this->user('alice@example.com');

        $team = Team::query()->create([
            'tenant_id' => $this->acme()->id,
            'name' => 'Ops Pod',
        ]);
        $team->members()->sync([$charlie->id, $alice->id]);

        $teamLeader = Role::query()->whereNull('tenant_id')->where('slug', 'team-leader')->firstOrFail();

        $membership = $charlie->memberships()->forTenant($this->acme()->id)->firstOrFail();
        $membership->roles()->syncWithoutDetaching($teamLeader->id);

        $this->actingAs($charlie);

        $this->get(route('deally.tasks.index'))
            ->assertOk()
            ->assertSee('Send spec sheet to Acme')
            ->assertSee('Review Call — Acme Corp')
            ->assertDontSee('Prep battle card for Acme');
    }

    public function test_solutions_lead_and_tenant_admin_see_everything(): void
    {
        $globex = Tenant::query()->where('slug', 'globex')->firstOrFail();
        $provisioner = app(DeallyTenantProvisioner::class);
        $provisioner->migrate($globex);
        $provisioner->seed($globex);

        $bob = $this->user('bob@example.com');

        $this->actingAs($bob);

        $this->get(route('deally.reporting'))->assertOk();
        $this->get(route('deally.teams.index'))->assertOk();
        $this->get(route('deally.activity.index'))->assertOk();
        $this->get(route('deally.pipeline'))->assertOk();
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
