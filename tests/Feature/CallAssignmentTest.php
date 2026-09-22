<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Calls\Models\Call;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class CallAssignmentTest extends TestCase
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

    public function test_sales_agent_can_start_their_own_call(): void
    {
        $charlie = $this->user('charlie@example.com');

        $this->actingAs($charlie)
            ->post(route('deally.calls.store'), [
                'name' => 'Pipeline health check',
                'company' => 'Acme Corp',
            ])->assertRedirect();

        $call = Call::query()->where('name', 'Pipeline health check')->firstOrFail();

        $this->assertSame((string) $charlie->id, (string) $call->owner_user_id);
        $this->assertSame(Call::STATUS_SCHEDULED, $call->status);

        $this->actingAs($charlie)
            ->get(route('deally.calls.live', $call))
            ->assertOk();
    }

    public function test_team_leader_can_create_a_call_and_assign_it_to_a_sales_agent(): void
    {
        $erica = $this->user('erica@example.com');
        $charlie = $this->user('charlie@example.com');

        $this->actingAs($erica)
            ->post(route('deally.calls.store'), [
                'name' => 'Onboarding follow-up',
                'company' => 'Globex Inc',
                'assignee_user_id' => $charlie->id,
            ])
            ->assertRedirect();

        $call = Call::query()->where('name', 'Onboarding follow-up')->firstOrFail();

        $this->assertSame((string) $charlie->id, (string) $call->owner_user_id);

        $this->actingAs($charlie)
            ->get(route('deally.calls.index'))
            ->assertOk()
            ->assertSee('Onboarding follow-up');

        $this->actingAs($charlie)
            ->get(route('deally.calls.live', $call))
            ->assertOk();

        $this->actingAs($erica)
            ->get(route('deally.calls.index'))
            ->assertOk()
            ->assertSee('Onboarding follow-up')
            ->assertSee('Charlie Lee');
    }

    public function test_manager_assigns_a_task_to_a_sales_agent(): void
    {
        $alice = $this->user('alice@example.com');
        $charlie = $this->user('charlie@example.com');

        $this->actingAs($alice)
            ->post(route('deally.tasks.store'), [
                'title' => 'Send pricing to Wayne',
                'linked_company' => 'Wayne Enterprises',
                'assignee_user_id' => $charlie->id,
            ])
            ->assertRedirect();

        $task = Task::query()->where('title', 'Send pricing to Wayne')->firstOrFail();

        $this->assertSame((string) $charlie->id, (string) $task->owner_user_id);
        $this->assertSame('Charlie Lee', $task->assignee);

        $this->actingAs($charlie)
            ->get(route('deally.tasks.index'))
            ->assertOk()
            ->assertSee('Send pricing to Wayne')
            ->assertDontSee('Send pricing to Globex');
    }

    public function test_team_leader_demo_user_sees_teams_but_cannot_manage_roles(): void
    {
        $erica = $this->user('erica@example.com');

        $this->actingAs($erica)->get(route('deally.teams.index'))->assertOk()->assertSee('East Pod');
        $this->actingAs($erica)->get(route('deally.roles.index'))->assertForbidden();
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
