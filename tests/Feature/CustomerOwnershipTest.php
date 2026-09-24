<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Core\Models\Team;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Pipeline\Models\Customer;
use Deally\Pipeline\Models\Opportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class CustomerOwnershipTest extends TestCase
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
        foreach (glob(database_path('tenants').DIRECTORY_SEPARATOR.'*.sqlite') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_a_new_deal_creates_a_customer_owned_by_the_creating_agent(): void
    {
        $charlie = $this->user('charlie@example.com');

        $this->actingAs($charlie)
            ->post(route('deally.pipeline.store'), [
                'company' => 'Umbrella Corp',
                'contact_name' => 'Admin Redding',
                'contact_title' => 'COO',
                'packages' => 'Pro Plan (1)',
                'stage' => 'discovery',
                'value' => 15000,
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $customer = Customer::query()->where('company', 'Umbrella Corp')->firstOrFail();

        $this->assertSame((string) $charlie->id, (string) $customer->owner_user_id);

        $deal = Opportunity::query()->where('company', 'Umbrella Corp')->firstOrFail();

        $this->assertSame($customer->getKey(), $deal->customer_id);
        $this->assertSame((string) $charlie->id, (string) $deal->owner_user_id);
    }

    public function test_a_deal_attaches_to_an_existing_customer_without_changing_its_owner(): void
    {
        $alice = $this->user('alice@example.com');
        $charlie = $this->user('charlie@example.com');

        $acme = Customer::query()->where('company', 'Acme Corp')->firstOrFail();

        $this->assertSame((string) $alice->id, (string) $acme->owner_user_id);
        $acmeId = $acme->getKey();

        $this->actingAs($charlie)
            ->post(route('deally.pipeline.store'), [
                'company' => 'Acme Corp',
                'packages' => 'Pro Plan (1)',
                'stage' => 'demo',
                'value' => 9000,
            ])
            ->assertRedirect();

        $this->assertSame(1, Customer::query()->where('company', 'Acme Corp')->count());

        $deal = Opportunity::query()
            ->where('company', 'Acme Corp')
            ->where('value', 9000)
            ->firstOrFail();

        $this->assertSame($acmeId, $deal->customer_id);
        $this->assertSame((string) $alice->id, (string) $deal->customer->owner_user_id);
    }

    public function test_deal_visibility_inherits_customer_ownership(): void
    {
        $alice = $this->user('alice@example.com');
        $charlie = $this->user('charlie@example.com');

        $customer = Customer::query()->create([
            'company' => 'Zeta Labs',
            'owner_user_id' => $alice->id,
        ]);

        Opportunity::query()->create([
            'customer_id' => $customer->getKey(),
            'company' => 'Zeta Labs',
            'stage' => 'demo',
            'value' => 20000,
        ]);

        $deal = Opportunity::query()->where('company', 'Zeta Labs')->firstOrFail();

        $this->assertSame((string) $alice->id, (string) $deal->owner_user_id);

        // The owning agent sees the deal through the customer.
        $this->actingAs($alice)
            ->get(route('deally.pipeline'))
            ->assertOk()
            ->assertSee('Zeta Labs');

        // Another agent never sees it — ownership lives on the customer.
        $this->actingAs($charlie)
            ->get(route('deally.pipeline'))
            ->assertOk()
            ->assertDontSee('Zeta Labs');
    }

    public function test_team_leader_sees_only_their_own_teams_deals(): void
    {
        $charlie = $this->user('charlie@example.com');
        $alice = $this->user('alice@example.com');
        $bob = $this->user('bob@example.com');

        // Charlie leads Team Alpha; Bob's customers live in Team Beta.
        DB::table('team_user')->where('user_id', $charlie->id)->delete();

        $teamAlpha = Team::query()->create(['tenant_id' => $this->acme()->id, 'name' => 'Team Alpha']);
        $teamAlpha->members()->attach([$charlie->id, $alice->id]);

        $teamBeta = Team::query()->create(['tenant_id' => $this->acme()->id, 'name' => 'Team Beta']);
        $teamBeta->members()->attach([$bob->id]);

        $visibleCustomer = Customer::query()->create([
            'company' => 'Visible Co',
            'owner_user_id' => $alice->id,
            'team_id' => $teamAlpha->id,
        ]);
        Opportunity::query()->create([
            'customer_id' => $visibleCustomer->getKey(),
            'company' => 'Visible Co',
            'stage' => 'demo',
            'value' => 1000,
        ]);

        $hiddenCustomer = Customer::query()->create([
            'company' => 'Hidden Co',
            'owner_user_id' => $bob->id,
            'team_id' => $teamBeta->id,
        ]);
        Opportunity::query()->create([
            'customer_id' => $hiddenCustomer->getKey(),
            'company' => 'Hidden Co',
            'stage' => 'demo',
            'value' => 2000,
        ]);

        $membership = $charlie->getMembershipForTenant($this->acme());
        $teamLeader = Role::query()->whereNull('tenant_id')->where('slug', 'team-leader')->firstOrFail();
        $membership->roles()->syncWithoutDetaching($teamLeader->id);

        $this->actingAs($charlie)
            ->get(route('deally.pipeline'))
            ->assertOk()
            ->assertSee('Visible Co')
            ->assertDontSee('Hidden Co');
    }

    private function acme(): Tenant
    {
        return Tenant::query()->where('slug', 'acme-corp')->firstOrFail();
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
