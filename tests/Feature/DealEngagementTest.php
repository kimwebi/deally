<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Pipeline\Models\Opportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaasFoundation\Models\Activity;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class DealEngagementTest extends TestCase
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

    public function test_deal_page_shows_the_engagement_log_with_calls_proposals_and_flags(): void
    {
        $alice = $this->user('alice@example.com');
        $deal = $this->deal('Acme Corp');

        $this->actingAs($alice)
            ->get(route('deally.deals.show', $deal))
            ->assertOk()
            ->assertSee('Engagement log')
            ->assertSee('Demo & Discovery')
            ->assertSee('Acme Enterprise v2')
            // Acme has a viewed (pending) proposal and an open deal, so it is
            // a critical account → its open deal is flagged as possibly lost.
            ->assertSee('Possible lost deal');
    }

    public function test_updating_a_deal_stage_is_logged_permanently(): void
    {
        $alice = $this->user('alice@example.com');
        $deal = $this->deal('Acme Corp');

        $this->actingAs($alice)
            ->patch(route('deally.deals.stage', $deal), ['stage' => 'demo'])
            ->assertSessionHas('toast');

        $this->assertSame('demo', $deal->fresh()->stage);

        $logged = Activity::query()
            ->where('subject_type', $deal->getMorphClass())
            ->where('subject_id', $deal->getKey())
            ->where('event', 'opportunity.stage')
            ->exists();

        $this->assertTrue($logged);
    }

    public function test_moving_a_deal_to_lost_requires_a_reason(): void
    {
        $alice = $this->user('alice@example.com');
        $deal = $this->deal('Acme Corp');

        $this->actingAs($alice)
            ->patch(route('deally.deals.stage', $deal), ['stage' => 'lost', 'lost_reason' => null])
            ->assertSessionHasErrors('lost_reason');

        $this->assertSame('negotiation', $deal->fresh()->stage);
    }

    public function test_moving_a_deal_to_lost_stores_the_reason_and_logs_a_warning(): void
    {
        $alice = $this->user('alice@example.com');
        $deal = $this->deal('Acme Corp');

        $this->actingAs($alice)
            ->patch(route('deally.deals.stage', $deal), ['stage' => 'lost', 'lost_reason' => 'Chose a competitor'])
            ->assertSessionHas('toast');

        $deal->refresh();

        $this->assertSame('lost', $deal->stage);
        $this->assertSame('Chose a competitor', $deal->lost_reason);

        $this->assertTrue(
            Activity::query()
                ->where('subject_type', $deal->getMorphClass())
                ->where('subject_id', $deal->getKey())
                ->where('event', 'opportunity.stage')
                ->where('properties->level', 'warning')
                ->exists()
        );
    }

    public function test_a_note_can_be_added_to_the_engagement_log(): void
    {
        $alice = $this->user('alice@example.com');
        $deal = $this->deal('Acme Corp');

        $this->actingAs($alice)
            ->post(route('deally.deals.notes', $deal), ['note' => 'Chasing the CSO on budget sign-off.'])
            ->assertSessionHas('toast');

        $this->assertTrue(
            Activity::query()
                ->where('subject_type', $deal->getMorphClass())
                ->where('subject_id', $deal->getKey())
                ->where('event', 'opportunity.note')
                ->where('description', 'Chasing the CSO on budget sign-off.')
                ->exists()
        );
    }

    public function test_a_viewer_cannot_change_a_deal_stage(): void
    {
        $viewer = $this->user('support@example.com');
        $deal = $this->deal('Acme Corp');

        $this->actingAs($viewer)
            ->patch(route('deally.deals.stage', $deal), ['stage' => 'demo'])
            ->assertForbidden();
    }

    private function user(string $email)
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function deal(string $company): Opportunity
    {
        return Opportunity::query()->where('company', $company)->firstOrFail();
    }
}
