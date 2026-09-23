<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Calls\Models\Call;
use Deally\Core\Models\Team;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantDatabaseManager;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaasFoundation\Models\AuditLog;
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

    public function test_tenant_owner_can_configure_integrations(): void
    {
        $this->actingAs($this->user('bob@example.com'));

        $this->get(route('deally.integrations.index'))
            ->assertOk()
            ->assertSee('Integrations')
            ->assertSee('Slack');

        $this->post(route('deally.integrations.update'), ['enabled' => ['slack', 'openai']])
            ->assertRedirect();

        $this->assertSame(
            ['slack', 'openai'],
            $this->acme()->fresh()->settings['integrations']
        );

        $this->get(route('deally.integrations.index'))
            ->assertOk()
            ->assertSee('Connected');
    }

    public function test_sales_agent_cannot_access_integrations_or_platform_console(): void
    {
        $this->actingAs($this->user('charlie@example.com'));

        $this->get(route('deally.integrations.index'))->assertForbidden();
        $this->get(route('central.setup.index'))->assertForbidden();
        $this->get(route('central.audit.index'))->assertForbidden();
    }

    public function test_tenant_admin_cannot_access_setup_console(): void
    {
        $this->actingAs($this->user('bob@example.com'))
            ->get(route('central.setup.index'))
            ->assertForbidden();
    }

    public function test_platform_support_can_use_setup_console(): void
    {
        $this->actingAs($this->user('support@example.com'))
            ->get(route('central.setup.index'))
            ->assertOk()
            ->assertSee('Setup Console')
            ->assertSee('Acme Corp');
    }

    public function test_superadmin_can_open_setup_console(): void
    {
        $this->actingAs($this->makeSuperadmin())
            ->get(route('central.setup.index'))
            ->assertOk()
            ->assertSee('Setup Console');
    }

    public function test_platform_support_can_provision_a_new_customer(): void
    {
        $this->actingAs($this->user('support@example.com'));

        $this->post(route('central.setup.tenants.store'), [
            'name' => 'Umbrella Corp',
            'email' => 'owner.umbrella@example.com',
        ])->assertRedirect();

        $tenant = Tenant::query()->where('slug', 'umbrella-corp')->firstOrFail();
        $this->assertSame('ready', $tenant->provisioning_status);

        $manager = app(DeallyTenantDatabaseManager::class);
        $this->assertFileExists(database_path('tenants').DIRECTORY_SEPARATOR.$manager->getTenantDatabaseName($tenant).'.sqlite');

        $membership = $tenant->memberships()
            ->whereHas('user', fn ($query) => $query->where('email', 'owner.umbrella@example.com'))
            ->firstOrFail();
        $this->assertTrue($membership->hasRole('owner'));
    }

    public function test_solutions_lead_sees_voc_trends_from_call_data(): void
    {
        $this->actingAs($this->user('david@example.com'));

        $this->get(route('deally.solutions.index'))
            ->assertOk()
            ->assertSee('Gap Queue')
            ->assertSee('Voice of customer')
            ->assertSee('Last 30 days:')
            ->assertSee('1 positive')
            ->assertSee('1 negative');
    }

    public function test_platform_support_sees_platform_wide_audit_logs(): void
    {
        $globex = Tenant::query()->where('slug', 'globex')->firstOrFail();

        AuditLog::query()->create([
            'tenant_id' => $globex->id,
            'user_id' => $this->user('alice@example.com')->id,
            'action' => 'tenant.created',
            'auditable_type' => 'tenants',
            'auditable_id' => $globex->id,
        ]);

        $this->actingAs($this->user('support@example.com'))
            ->get(route('central.audit.index'))
            ->assertOk()
            ->assertSee('Globex')
            ->assertSee('tenant.created');

        $this->actingAs($this->user('bob@example.com'))
            ->get(route('central.audit.index'))
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

    private function makeSuperadmin(): User
    {
        return User::unguarded(fn () => User::create([
            'name' => 'Super Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => true,
        ]));
    }
}
