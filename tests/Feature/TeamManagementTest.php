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

class TeamManagementTest extends TestCase
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

    public function test_owner_can_create_and_see_teams(): void
    {
        $alice = $this->user('alice@example.com');
        $charlie = $this->user('charlie@example.com');

        $this->actingAs($alice)
            ->post(route('deally.teams.store'), [
                'name' => 'West Pod',
                'description' => 'West coast accounts.',
                'members' => [$alice->id, $charlie->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('teams', ['tenant_id' => $this->acme()->id, 'name' => 'West Pod']);

        $team = Team::query()->where('name', 'West Pod')->firstOrFail();
        $this->assertCount(2, $team->members);

        $this->actingAs($alice)
            ->get(route('deally.teams.index'))
            ->assertOk()
            ->assertSee('West Pod')
            ->assertSee('East Pod');
    }

    public function test_sales_agent_cannot_view_or_manage_teams(): void
    {
        $this->actingAs($this->user('charlie@example.com'));

        $this->get(route('deally.teams.index'))->assertForbidden();

        $this->post(route('deally.teams.store'), ['name' => 'Nope'])->assertForbidden();
    }

    public function test_team_leader_can_view_but_not_manage_teams(): void
    {
        $charlie = $this->user('charlie@example.com');
        $teamLeader = Role::query()->whereNull('tenant_id')->where('slug', 'team-leader')->firstOrFail();

        $charlie->memberships()->forTenant($this->acme()->id)->firstOrFail()
            ->roles()->syncWithoutDetaching($teamLeader->id);

        $this->actingAs($charlie);

        $this->get(route('deally.teams.index'))->assertOk()->assertSee('East Pod');
        $this->post(route('deally.teams.store'), ['name' => 'Nope'])->assertForbidden();
    }

    public function test_team_from_another_tenant_cannot_be_managed(): void
    {
        $globex = Tenant::query()->where('slug', 'globex')->firstOrFail();
        $globexTeam = Team::query()->create(['tenant_id' => $globex->id, 'name' => 'West Pod']);

        $this->actingAs($this->user('alice@example.com'))
            ->delete(route('deally.teams.destroy', $globexTeam))
            ->assertNotFound();

        $this->assertDatabaseHas('teams', ['id' => $globexTeam->id]);
    }

    public function test_team_update_syncs_members(): void
    {
        $alice = $this->user('alice@example.com');
        $charlie = $this->user('charlie@example.com');
        $bob = $this->user('bob@example.com');

        $team = Team::query()->where('name', 'East Pod')->firstOrFail();
        $team->members()->sync([$alice->id]);

        $this->actingAs($alice)
            ->put(route('deally.teams.update', $team), [
                'name' => 'East Pod',
                'members' => [$charlie->id, $bob->id],
            ])
            ->assertRedirect();

        $this->assertSame(
            collect([$charlie->id, $bob->id])->sort()->values()->all(),
            $team->members()->orderBy('users.id')->pluck('user_id')->map(fn ($id): int => (int) $id)->sort()->values()->all()
        );
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
