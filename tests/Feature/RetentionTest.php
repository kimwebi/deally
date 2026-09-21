<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Deally\Calls\Models\Call;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Core\Services\TenantConnectionBinder;
use Deally\Pipeline\Models\Opportunity;
use Deally\Proposals\Models\Proposal;
use Deally\Retention\Models\RetentionSetting;
use Deally\Retention\Services\RetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class RetentionTest extends TestCase
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

    public function test_retention_setting_is_seeded_with_standard_tier(): void
    {
        $setting = RetentionSetting::query()->firstOrFail();

        $this->assertSame('standard', $setting->tier);
        $this->assertSame(3, $setting->months);
    }

    public function test_retention_service_archives_old_closed_deal_transcripts(): void
    {
        RetentionSetting::query()->update(['tier' => 'standard', 'months' => 3]);

        $opportunity = Opportunity::query()->create([
            'company' => 'Archived Toys',
            'contact_name' => 'Ffi Foche',
            'stage' => 'won',
            'value' => 9999,
        ]);

        $oldCall = Call::query()->create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Old Discovery',
            'company' => 'Archived Toys',
            'date' => now()->subMonths(4)->toDateString(),
            'duration' => '12m',
            'sentiment' => 'neutral',
        ]);

        $recentCall = Call::query()->create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Recent Check-in',
            'company' => 'Archived Toys',
            'date' => now()->toDateString(),
            'duration' => '12m',
            'sentiment' => 'neutral',
        ]);

        $service = app(RetentionService::class);

        $this->assertTrue($service->isTranscriptArchived($oldCall));
        $this->assertFalse($service->isTranscriptArchived($recentCall));

        $this->actingAs($this->alice())->get(route('deally.calls.show', $oldCall))->assertOk();
        $this->actingAs($this->alice())->get(route('deally.calls.show', $recentCall))->assertOk()->assertDontSee('Call transcripts and proposals archived');
    }

    public function test_open_deal_transcripts_are_not_archived(): void
    {
        RetentionSetting::query()->update(['tier' => 'standard', 'months' => 3]);

        $opportunity = Opportunity::query()->create([
            'company' => 'Still Open',
            'contact_name' => 'Ffi Foche',
            'stage' => 'negotiation',
            'value' => 5000,
        ]);

        $oldCall = Call::query()->create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Old Negotiation',
            'company' => 'Still Open',
            'date' => now()->subMonths(5)->toDateString(),
            'duration' => '12m',
            'sentiment' => 'neutral',
        ]);

        $this->assertFalse(app(RetentionService::class)->isTranscriptArchived($oldCall));
    }

    public function test_calls_page_hides_archived_links(): void
    {
        RetentionSetting::query()->update(['tier' => 'standard', 'months' => 3]);

        $opportunity = Opportunity::query()->create([
            'company' => 'Old Won Co',
            'contact_name' => 'Ffi Foche',
            'stage' => 'won',
            'value' => 3000,
        ]);

        Call::query()->create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Won Call',
            'company' => 'Old Won Co',
            'date' => now()->subMonths(4)->toDateString(),
            'duration' => '10m',
            'sentiment' => 'positive',
        ]);

        $this->actingAs($this->alice())
            ->get(route('deally.calls.index'))
            ->assertOk()
            ->assertSee('Archived')
            ->assertSee('Call transcripts and proposals archived');
    }

    public function test_proposals_page_marks_archived_proposals(): void
    {
        RetentionSetting::query()->update(['tier' => 'standard', 'months' => 3]);

        $opportunity = Opportunity::query()->create([
            'company' => 'Proposal Target Co',
            'contact_name' => 'Ffi Foche',
            'stage' => 'won',
            'value' => 9000,
        ]);
        Opportunity::query()->where('id', $opportunity->id)->update(['updated_at' => now()->subMonths(4)]);

        $proposal = Proposal::query()->create([
            'name' => 'Archived Proposal',
            'company' => 'Proposal Target Co',
            'value' => 9000,
            'status' => 'approved',
        ]);
        Proposal::query()->where('id', $proposal->id)->update(['updated_at' => now()->subMonths(4)]);

        $this->actingAs($this->alice())
            ->get(route('deally.proposals.index'))
            ->assertOk()
            ->assertSee('Archived')
            ->assertSee('Contact admin to retrieve.');
    }

    public function test_settings_shows_read_only_retention_tier(): void
    {
        $this->actingAs($this->alice())
            ->get(route('deally.settings.index'))
            ->assertOk()
            ->assertSee('Data Retention tier')
            ->assertSee('Standard · 3 months');
    }

    private function alice(): User
    {
        return User::query()->where('email', 'alice@example.com')->firstOrFail();
    }
}
