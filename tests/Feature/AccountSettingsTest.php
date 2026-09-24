<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Pipeline\Models\AccountSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
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

    public function test_the_demand_threshold_is_seeded_with_a_default(): void
    {
        $this->assertSame(150000, AccountSetting::current()->demandThreshold());
    }

    public function test_an_owner_can_update_the_demand_threshold(): void
    {
        $alice = $this->user('alice@example.com');

        $this->actingAs($alice)
            ->put(route('deally.settings.update'), [
                'name' => $alice->name,
                'email' => $alice->email,
                'demand_pipeline_threshold' => 250000,
            ])
            ->assertSessionHas('toast');

        $this->assertSame(250000, AccountSetting::current()->demandThreshold());
    }

    public function test_the_threshold_field_is_only_shown_to_accounts_that_can_manage_it(): void
    {
        $alice = $this->user('alice@example.com');
        $charlie = $this->user('charlie@example.com');

        $this->actingAs($alice)
            ->get(route('deally.settings.index'))
            ->assertOk()
            ->assertSee('demand_pipeline_threshold');

        $this->actingAs($charlie)
            ->get(route('deally.settings.index'))
            ->assertOk()
            ->assertDontSee('demand_pipeline_threshold');
    }

    public function test_a_sales_agent_cannot_change_the_demand_threshold(): void
    {
        $charlie = $this->user('charlie@example.com');

        $this->actingAs($charlie)
            ->put(route('deally.settings.update'), [
                'name' => $charlie->name,
                'email' => $charlie->email,
                'demand_pipeline_threshold' => 999999,
            ])
            ->assertSessionHas('toast');

        $this->assertSame(150000, AccountSetting::current()->demandThreshold());
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
