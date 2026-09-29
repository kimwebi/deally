<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Deally\Calls\Models\Call;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Core\Services\TenantConnectionBinder;
use Deally\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class TaskDueTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);

        $acme = Tenant::query()->where('slug', 'acme-corp')->firstOrFail();
        $provisioner = app(DeallyTenantProvisioner::class);
        $provisioner->migrate($acme);
        $provisioner->seed($acme);

        $membership = $this->alice()->memberships()->active()->with('tenant')->first();

        app('db')->purge('deally');
        app(TenantConnectionBinder::class)->bind($membership->tenant);
    }

    protected function tearDown(): void
    {
        foreach (glob(database_path('tenants').DIRECTORY_SEPARATOR.'*.sqlite') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_task_create_stores_assignee_and_time_aware_due(): void
    {
        $this->actingAs($this->alice())
            ->post(route('deally.tasks.store'), [
                'title' => 'Send proposal to Wayne',
                'assignee' => 'Alice Johnson',
                'linked_company' => 'Wayne Enterprises',
                'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHas('toast');

        $task = Task::query()->where('title', 'Send proposal to Wayne')->firstOrFail();

        $this->assertSame('Alice Johnson', $task->assignee);
        $this->assertSame('todo', $task->status);
        $this->assertTrue($task->due_at->isFuture());
    }

    public function test_task_modal_exposes_review_url_for_review_call_tasks(): void
    {
        $reviewTask = Task::query()->where('title', 'Review Call — Acme Corp')->firstOrFail();
        $call = Call::query()->where('company', 'Acme Corp')->orderByDesc('created_at')->firstOrFail();

        $response = $this->actingAs($this->alice())->get(route('deally.tasks.index'))->assertOk();

        $response->assertSee('data-review-url="'.route('deally.calls.review', $call).'"', false);
    }

    public function test_task_list_shows_due_time(): void
    {
        $reviewTask = Task::query()->where('title', 'Review Call — Acme Corp')->firstOrFail();

        $this->actingAs($this->alice())
            ->get(route('deally.tasks.index'))
            ->assertOk()
            ->assertSee($reviewTask->due_at->format('M d, g:ia'))
            ->assertSee('Review Call — Acme Corp');
    }

    /* ---------- every task carries an explicit due time ---------- */

    public function test_a_task_cannot_be_created_without_a_due_at(): void
    {
        $this->actingAs($this->alice())
            ->post(route('deally.tasks.store'), [
                'title' => 'No due moment',
            ])
            ->assertSessionHasErrors('due_at');

        $this->assertNull(Task::query()->where('title', 'No due moment')->first());
    }

    public function test_a_bare_date_is_not_a_sufficient_due_time(): void
    {
        $this->actingAs($this->alice())
            ->post(route('deally.tasks.store'), [
                'title' => 'Date only is not enough',
                'due_at' => now()->addDay()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('due_at');

        $this->assertNull(Task::query()->where('title', 'Date only is not enough')->first());
    }

    /* ---------- nothing is deleted: archiving a task ---------- */

    public function test_destroy_archives_a_task_instead_of_deleting_it(): void
    {
        $task = Task::query()->create([
            'title' => 'Archived legacy task',
            'due_at' => now()->addDay(),
            'status' => 'todo',
            'owner_user_id' => $this->alice()->id,
        ]);

        $this->actingAs($this->alice())
            ->delete(route('deally.tasks.destroy', $task))
            ->assertSessionHas('toast');

        // Still on record — just moved to the closed list.
        $this->assertSame('closed', $task->refresh()->status);
    }

    /* ---------- the review task shows the agent performance snapshot ---------- */

    public function test_review_task_modal_shows_the_agent_performance_snapshot(): void
    {
        $call = Call::query()->where('company', 'Acme Corp')->orderByDesc('created_at')->firstOrFail();

        $task = Task::query()->create([
            'title' => "Review Call — {$call->company}",
            'linked_company' => $call->company,
            'call_id' => $call->id,
            'due_at' => now()->addHours(24),
            'status' => 'todo',
            'owner_user_id' => $this->alice()->id,
        ]);

        $response = $this->actingAs($this->alice())
            ->get(route('deally.tasks.show', $task))
            ->assertOk();

        $response->assertSee('Agent Performance');
        $response->assertSee('% agent', false);
        $response->assertSee('Talk ratio', false);
        $response->assertSee('Objections handled', false);
    }

    private function alice(): User
    {
        return User::query()->where('email', 'alice@example.com')->firstOrFail();
    }
}
