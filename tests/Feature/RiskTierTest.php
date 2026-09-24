<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Pipeline\Models\AccountSetting;
use Deally\Pipeline\Models\Customer;
use Deally\Pipeline\Models\Opportunity;
use Deally\Pipeline\Models\ServiceReviewSchedule;
use Deally\Pipeline\Models\ServiceReviewSession;
use Deally\Pipeline\Services\RiskService;
use Deally\Proposals\Models\Proposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class RiskTierTest extends TestCase
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

    public function test_low_risk_for_a_customer_without_open_deals(): void
    {
        $customer = $this->customer('Quiet Co');

        $assessed = app(RiskService::class)->assess($customer);

        $this->assertSame(RiskService::TIER_LOW, $assessed['tier']);
        $this->assertFalse($assessed['at_risk']);
    }

    public function test_medium_risk_for_a_customer_with_an_open_deal(): void
    {
        $customer = $this->customer('Steady Co');
        Opportunity::query()->create([
            'customer_id' => $customer->getKey(),
            'company' => 'Steady Co',
            'stage' => 'discovery',
            'value' => 20000,
        ]);

        $assessed = app(RiskService::class)->assess($customer);

        $this->assertSame(RiskService::TIER_MEDIUM, $assessed['tier']);
        $this->assertFalse($assessed['at_risk']);
    }

    public function test_high_risk_for_a_demand_account_with_a_negotiation_deal(): void
    {
        $customer = $this->customer('Demand Co');

        // Above the default 150000 demand threshold, in the negotiation stage.
        Opportunity::query()->create([
            'customer_id' => $customer->getKey(),
            'company' => 'Demand Co',
            'stage' => 'negotiation',
            'value' => 300000,
        ]);

        $assessed = app(RiskService::class)->assess($customer);

        $this->assertSame(RiskService::TIER_HIGH, $assessed['tier']);
        $this->assertTrue($assessed['at_risk']);
        $this->assertTrue($assessed['demand_account']);
    }

    public function test_critical_risk_when_a_service_review_session_is_missed(): void
    {
        $customer = $this->customer('Lapsing Co');

        $schedule = ServiceReviewSchedule::query()->create([
            'customer_id' => $customer->getKey(),
            'cadence_days' => 30,
            'status' => ServiceReviewSchedule::STATUS_ACTIVE,
            'started_at' => now(),
        ]);

        ServiceReviewSession::query()->create([
            'schedule_id' => $schedule->getKey(),
            'scheduled_at' => now()->subDays(3),
            'status' => ServiceReviewSession::STATUS_SCHEDULED,
        ]);

        $assessed = app(RiskService::class)->assess($customer);

        $this->assertSame(RiskService::TIER_CRITICAL, $assessed['tier']);
        $this->assertTrue($assessed['at_risk']);
        $this->assertSame(1, $assessed['missed_sessions']);
        $this->assertContains('1 missed Service Review session', $assessed['reasons']);
    }

    public function test_critical_risk_when_a_proposal_needs_action(): void
    {
        $customer = $this->customer('Pending Co');

        Proposal::query()->create([
            'company' => 'Pending Co',
            'name' => 'Awaiting sign-off',
            'status' => 'viewed',
            'owner_user_id' => 1,
        ]);

        $assessed = app(RiskService::class)->assess($customer);

        $this->assertSame(RiskService::TIER_CRITICAL, $assessed['tier']);
        $this->assertTrue($assessed['at_risk']);
        $this->assertContains("Proposal 'Awaiting sign-off' is viewed and needs an owner to act on it", $assessed['reasons']);
    }

    public function test_demand_threshold_is_configurable_per_account(): void
    {
        AccountSetting::current()->update(['demand_pipeline_threshold' => 50000]);

        // 60000 of open pipeline exceeds the lowered threshold.
        $customer = $this->customer('Customised Co');

        Opportunity::query()->create([
            'customer_id' => $customer->getKey(),
            'company' => 'Customised Co',
            'stage' => 'negotiation',
            'value' => 60000,
        ]);

        $assessed = app(RiskService::class)->assess($customer);

        $this->assertTrue($assessed['demand_account']);
        $this->assertSame(RiskService::TIER_HIGH, $assessed['tier']);
        $this->assertSame(50000, AccountSetting::current()->demandThreshold());
    }

    public function test_assessments_are_keyed_by_customer_id(): void
    {
        $one = $this->customer('Alpha Ltd');
        $two = $this->customer('Beta Ltd');

        Opportunity::query()->create([
            'customer_id' => $one->getKey(),
            'company' => 'Alpha Ltd',
            'stage' => 'discovery',
            'value' => 15000,
        ]);

        $assessments = app(RiskService::class)->assessments(collect([$one, $two]));

        $this->assertSame(RiskService::TIER_MEDIUM, $assessments->get($one->getKey())['tier']);
        $this->assertSame(RiskService::TIER_LOW, $assessments->get($two->getKey())['tier']);
    }

    private function customer(string $company): Customer
    {
        return Customer::query()->create([
            'company' => $company,
            'owner_user_id' => 1,
        ]);
    }
}
