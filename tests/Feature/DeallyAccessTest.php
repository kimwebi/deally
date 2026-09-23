<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Calls\Models\Call;
use Deally\Core\Models\Team;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaasFoundation\Models\Permission;
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

    public function test_sales_agent_sees_only_their_own_tasks_on_call_pages(): void
    {
        $charlie = $this->user('charlie@example.com');

        $call = Call::query()->create([
            'name' => 'Pipeline health check',
            'company' => 'Acme Corp',
            'date' => now(),
            'duration' => '0m',
            'sentiment' => 'neutral',
            'status' => Call::STATUS_SCHEDULED,
            'owner_user_id' => $charlie->id,
        ]);

        $this->actingAs($charlie)
            ->get(route('deally.calls.live', $call))
            ->assertOk()
            ->assertSee('Send spec sheet to Acme')
            ->assertDontSee('Review Call — Acme Corp')
            ->assertDontSee('Prep battle card for Acme');

        $this->actingAs($charlie)
            ->get(route('deally.calls.summary', $call))
            ->assertOk()
            ->assertSee('24 hours')
            ->assertDontSee('due '.today()->setTime(17, 0)->format('M d, g:ia'));

        $this->actingAs($charlie)
            ->post(route('deally.calls.end', $call), [
                'duration' => '12:00',
                'sentiment' => 'neutral',
                'notes' => '',
            ])
            ->assertRedirect(route('deally.calls.summary', $call));

        $task = Task::query()->where('title', 'Review Call — Acme Corp')->latest('id')->firstOrFail();
        $this->assertSame((string) $charlie->id, (string) $task->owner_user_id);

        $this->actingAs($charlie)
            ->get(route('deally.tasks.index'))
            ->assertOk()
            ->assertSee('Review Call — Acme Corp');
    }

    public function test_team_tasks_page_is_seat_scoped(): void
    {
        $this->actingAs($this->user('charlie@example.com'))
            ->get(route('deally.reporting.tasks'))
            ->assertOk()
            ->assertSee('Send spec sheet to Acme')
            ->assertDontSee('Review Call — Acme Corp')
            ->assertDontSee('Prep battle card for Acme')
            ->assertDontSee('Send pricing to Globex')
            ->assertDontSee('Alice Johnson')
            ->assertDontSee('Bob Carter');
    }

    public function test_reassigning_permission_via_role_sync_restores_access(): void
    {
        $reporting = Permission::query()->where('slug', 'deally.reporting.view')->firstOrFail();

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'viewer')->firstOrFail();
        $role->permissions()->sync(
            $role->permissions()->pluck('permissions.id')->push($reporting->id)
        );

        $this->actingAs($this->user('charlie@example.com'))
            ->get(route('deally.reporting'))
            ->assertOk();
    }

    public function test_reassign_via_roles_ui_restores_access(): void
    {
        $reporting = Permission::query()->where('slug', 'deally.reporting.view')->firstOrFail();

        $viewer = Role::query()->whereNull('tenant_id')->where('slug', 'viewer')->firstOrFail();
        $viewer->load('permissions');

        $this->actingAs($this->user('bob@example.com'))
            ->put(route('deally.roles.update', $viewer), [
                'name' => 'Viewer',
                'description' => $viewer->description,
                'permissions' => array_merge(
                    $viewer->permissions->pluck('id')->all(),
                    [$reporting->id]
                ),
            ])
            ->assertRedirect(route('deally.roles.index'));

        $this->actingAs($this->user('charlie@example.com'))
            ->get(route('deally.reporting'))
            ->assertOk();
    }

    public function test_agent_can_use_seat_features_but_not_admin_areas(): void
    {
        $this->actingAs($this->user('charlie@example.com'));

        $this->get(route('deally.workspace'))->assertOk();
        $this->get(route('deally.kb.index'))->assertOk();
        $this->post(route('deally.tasks.store'), ['title' => 'Follow up Charlie'])->assertRedirect();

        $this->get(route('deally.solutions.index'))->assertForbidden();

        $this->get(route('deally.reporting'))->assertOk()->assertSee('Acme Corp');
        $this->get(route('deally.reporting.tasks'))->assertOk();
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

    public function test_globex_administrator_sees_everything(): void
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
        $this->get(route('deally.solutions.index'))->assertOk()->assertSee('Gap Queue');
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
