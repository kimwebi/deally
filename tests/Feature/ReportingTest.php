<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Deally\Calls\Models\Call;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Core\Services\TenantConnectionBinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class ReportingTest extends TestCase
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

    public function test_team_performance_dashboard_render(): void
    {
        $this->actingAs($this->alice())
            ->get(route('deally.reporting'))
            ->assertOk()
            ->assertSee('Team Performance')
            ->assertSee('AI Effectiveness')
            ->assertSee('Sentiment Trend');
    }

    public function test_account_story_render_for_known_company(): void
    {
        $this->actingAs($this->alice())
            ->get(route('deally.reporting.account', ['company' => 'Acme Corp']))
            ->assertOk()
            ->assertSee('Acme Corp')
            ->assertSee('Recent conversation summaries');
    }

    public function test_team_tasks_overview_lists_assignees_and_filters(): void
    {
        $this->actingAs($this->alice())
            ->get(route('deally.reporting.tasks'))
            ->assertOk()
            ->assertSee('Team Task Overview')
            ->assertSee('Alice Johnson')
            ->assertSee('Bob Carter');

        $this->actingAs($this->alice())
            ->get(route('deally.reporting.tasks', ['assignee' => 'Bob Carter']))
            ->assertOk()
            ->assertSee('Prep battle card for Acme')
            ->assertDontSee('Send pricing to Globex');
    }

    public function test_coaching_review_render_for_a_call(): void
    {
        $call = Call::query()->where('company', 'Acme Corp')->firstOrFail();

        $this->actingAs($this->alice())
            ->get(route('deally.reporting.coaching', $call))
            ->assertOk()
            ->assertSee('Coaching Review — Acme Corp')
            ->assertSee('Agent performance score')
            ->assertSee('Three key moments');
    }

    private function alice(): User
    {
        return User::query()->where('email', 'alice@example.com')->firstOrFail();
    }
}
