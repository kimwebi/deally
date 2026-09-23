<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Calls\Models\Call;
use Deally\Core\Models\Team;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Proposals\Models\Proposal;
use Deally\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class WorkspaceRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([DemoSeeder::class, DeallyAccessSeeder::class]);

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

    public function test_agent_gets_personal_workspace(): void
    {
        $charlie = User::query()->where('email', 'charlie@example.com')->firstOrFail();

        $this->actingAs($charlie)->get(route('deally.workspace'))
            ->assertOk()
            ->assertSee('Opportunities')
            ->assertSee('＋ Event')
            ->assertSee('＋ Task')
            ->assertDontSee('Team Pulse')
            ->assertDontSee('Rep Leaderboard');
    }

    public function test_owner_gets_team_workspace(): void
    {
        $alice = User::query()->where('email', 'alice@example.com')->firstOrFail();

        $this->actingAs($alice)->get(route('deally.workspace'))
            ->assertOk()
            ->assertSee('Team Pulse')
            ->assertSee('Rep Leaderboard')
            ->assertSee('Awaiting Approval')
            ->assertSee('Coaching Flags')
            ->assertSee('New Event')
            ->assertSee('Create Event');
    }

    public function test_team_leader_gets_team_workspace(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'dana@example.com'],
            ['name' => 'Dana Smith', 'password' => 'password', 'is_active' => true]
        );

        $acme = Tenant::query()->where('slug', 'acme-corp')->firstOrFail();

        $membership = Membership::query()->firstOrCreate(
            ['user_id' => $user->id, 'tenant_id' => $acme->id],
            ['status' => Membership::STATUS_ACTIVE, 'joined_at' => now()]
        );

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'team-leader')->firstOrFail();
        $membership->roles()->syncWithoutDetaching($role->id);

        $team = Team::query()->forTenant($acme->id)->firstOrFail();
        $team->members()->syncWithoutDetaching([$user->id]);

        $this->actingAs($user)->get(route('deally.workspace'))
            ->assertOk()
            ->assertSee('Team Pulse')
            ->assertSee('Team Calendar')
            ->assertSee('Alice Johnson');
    }

    public function test_team_leader_can_approve_proposal_from_workspace(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'dana@example.com'],
            ['name' => 'Dana Smith', 'password' => 'password', 'is_active' => true]
        );

        $acme = Tenant::query()->where('slug', 'acme-corp')->firstOrFail();

        $membership = Membership::query()->firstOrCreate(
            ['user_id' => $user->id, 'tenant_id' => $acme->id],
            ['status' => Membership::STATUS_ACTIVE, 'joined_at' => now()]
        );

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'team-leader')->firstOrFail();
        $membership->roles()->syncWithoutDetaching($role->id);

        $team = Team::query()->forTenant($acme->id)->firstOrFail();
        $team->members()->syncWithoutDetaching([$user->id]);

        $proposal = Proposal::query()->where('status', 'viewed')->firstOrFail();

        $this->actingAs($user)
            ->post(route('deally.proposals.status', $proposal), ['status' => 'approved'])
            ->assertRedirect();

        $this->assertSame('approved', $proposal->fresh()->status);
    }

    public function test_home_event_modal_creates_a_task(): void
    {
        $alice = User::query()->where('email', 'alice@example.com')->firstOrFail();

        $this->actingAs($alice)->post(route('deally.workspace.event'), [
            'type' => 'external',
            'title' => 'Internal Q2 sync',
            'linked_company' => 'Globex Inc',
            'due_at' => now()->addDay()->format('Y-m-d H:i'),
        ])->assertRedirect()->assertSessionHas('toast', 'External event added.');

        $this->assertNotNull(Task::query()->where('title', '[External] Internal Q2 sync')->first());
    }

    public function test_home_event_modal_schedules_a_deally_call(): void
    {
        $alice = User::query()->where('email', 'alice@example.com')->firstOrFail();

        $this->actingAs($alice)->post(route('deally.workspace.event'), [
            'type' => 'deally',
            'title' => 'Discovery call with Acme',
            'linked_company' => 'Acme Corp',
            'due_at' => now()->addHours(3)->format('Y-m-d H:i'),
        ])->assertRedirect()->assertSessionHas('toast', 'DeAlly call scheduled — open it from the calendar to start.');

        $call = Call::query()->where('name', 'Discovery call with Acme')->firstOrFail();

        $this->assertSame('Acme Corp', $call->company);
        $this->assertTrue($call->date->isToday());
        $this->assertNull(Task::query()->where('title', 'LIKE', '%Discovery call with Acme%')->first());
    }

    public function test_scheduled_deally_call_appears_on_calendar_and_links_to_live(): void
    {
        $charlie = User::query()->where('email', 'charlie@example.com')->firstOrFail();

        $call = Call::create([
            'name' => 'Demo & Discovery',
            'company' => 'Globex Inc',
            'date' => now()->setTime(14, 37)->format('Y-m-d H:i:s'),
            'duration' => '0m',
            'sentiment' => 'neutral',
            'owner_user_id' => $charlie->id,
        ]);

        $this->actingAs($charlie)
            ->get(route('deally.workspace'))
            ->assertOk()
            ->assertSeeText('Demo & Discovery')
            ->assertSee('/app/calls/'.$call->id.'/live')
            ->assertSee('14:37');
    }

    public function test_ending_a_scheduled_call_removes_it_from_the_calendar(): void
    {
        $charlie = User::query()->where('email', 'charlie@example.com')->firstOrFail();

        $call = Call::create([
            'name' => 'Quarterly checkpoint',
            'company' => 'Acme Corp',
            'date' => now()->setTime(15, 0)->format('Y-m-d H:i:s'),
            'duration' => '0m',
            'sentiment' => 'neutral',
            'owner_user_id' => $charlie->id,
        ]);

        $this->actingAs($charlie)
            ->get(route('deally.workspace'))
            ->assertOk()
            ->assertSeeText('Quarterly checkpoint')
            ->assertSee('/app/calls/'.$call->id.'/live');

        $this->actingAs($charlie)
            ->post(route('deally.calls.end', $call), [
                'duration' => '11:45',
                'sentiment' => 'neutral',
                'notes' => '',
            ])
            ->assertRedirect(route('deally.calls.summary', $call));

        $this->actingAs($charlie)
            ->get(route('deally.workspace'))
            ->assertOk()
            ->assertDontSee('/app/calls/'.$call->id.'/live');
    }

    public function test_home_task_modal_creates_a_task(): void
    {
        $alice = User::query()->where('email', 'alice@example.com')->firstOrFail();

        $this->actingAs($alice)->post(route('deally.tasks.store'), [
            'title' => 'Prep notes for the Acme call',
            'linked_company' => 'Acme Corp',
            'due_at' => now()->format('Y-m-d H:i'),
        ])->assertRedirect()->assertSessionHas('toast');

        $this->assertNotNull(Task::query()->where('title', 'Prep notes for the Acme call')->first());
    }
}
