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

    private function alice(): User
    {
        return User::query()->where('email', 'alice@example.com')->firstOrFail();
    }
}
