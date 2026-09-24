<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Pipeline\Models\Customer;
use Deally\Pipeline\Models\Opportunity;
use Deally\Pipeline\Services\ServiceReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class PipelinePageTest extends TestCase
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

    public function test_the_board_view_groups_deals_by_stage(): void
    {
        $alice = $this->user('alice@example.com');

        $this->actingAs($alice)
            ->get(route('deally.pipeline', ['view' => 'board']))
            ->assertOk()
            ->assertSee('Discovery')
            ->assertSee('Negotiation')
            ->assertSee('Acme Corp');
    }

    public function test_the_list_view_shows_the_owner_column(): void
    {
        $alice = $this->user('alice@example.com');

        $this->actingAs($alice)
            ->get(route('deally.pipeline'))
            ->assertOk()
            ->assertSee('Owner')
            ->assertSee('Alice Johnson');
    }

    public function test_stage_filter_narrows_the_list(): void
    {
        $alice = $this->user('alice@example.com');

        $this->actingAs($alice)
            ->get(route('deally.pipeline', ['stage' => 'won']))
            ->assertOk()
            ->assertSee('Globex Inc')
            // Acme Corp's deal lives in negotiation, so its contact must not
            // appear in the won-stage table (the picker lists customer names
            // regardless of stage, so we assert on the row's contact line).
            ->assertDontSee('Jane Doe · CTO');
    }

    public function test_new_deal_attaches_to_the_picked_customer_and_prompts_service_review(): void
    {
        $charlie = $this->user('charlie@example.com');
        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();

        $this->actingAs($charlie)
            ->post(route('deally.pipeline.store'), [
                'customer_id' => $stark->getKey(),
                'stage' => 'discovery',
                'value' => 45000,
            ])
            ->assertSessionHas('toast')
            ->assertSessionHas('service_review_setup_customer', $stark->getKey());

        $this->assertDatabaseHas('opportunities', [
            'customer_id' => $stark->getKey(),
            'company' => 'Stark Industries',
            'value' => 45000,
        ], 'deally');
    }

    public function test_service_review_prompt_is_withheld_once_a_schedule_exists(): void
    {
        $charlie = $this->user('charlie@example.com');
        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();

        app(ServiceReviewService::class)->setup($stark);

        $this->actingAs($charlie)
            ->post(route('deally.pipeline.store'), [
                'customer_id' => $stark->getKey(),
                'stage' => 'discovery',
                'value' => 26000,
            ])
            ->assertSessionHas('toast');

        $this->assertNull(session('service_review_setup_customer'));
    }

    public function test_a_deal_cannot_be_created_on_another_agents_customer(): void
    {
        $charlie = $this->user('charlie@example.com');
        $acme = Customer::query()->where('company', 'Acme Corp')->firstOrFail();

        $this->actingAs($charlie)
            ->post(route('deally.pipeline.store'), [
                'customer_id' => $acme->getKey(),
                'stage' => 'discovery',
                'value' => 10000,
            ])
            ->assertSessionHasErrors('customer_id');

        $this->assertSame(1, Opportunity::query()->where('company', 'Acme Corp')->count());
    }

    public function test_the_sidebar_pipeline_badge_is_scoped_to_the_seat(): void
    {
        // Charlie owns only Stark Industries, whose single deal is the only one
        // in their seat — the badge must show 1, not the tenant-wide total (4).
        $charlie = $this->user('charlie@example.com');

        $this->actingAs($charlie)
            ->get(route('deally.pipeline'))
            ->assertOk()
            ->assertSeeHtml('<span class="nav-badge">1</span>')
            ->assertDontSeeHtml('<span class="nav-badge">4</span>');
    }

    public function test_the_sidebar_pipeline_badge_shows_the_tenant_total_for_owners(): void
    {
        $alice = $this->user('alice@example.com');

        $this->actingAs($alice)
            ->get(route('deally.pipeline'))
            ->assertOk()
            ->assertSeeHtml('<span class="nav-badge">4</span>');
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
