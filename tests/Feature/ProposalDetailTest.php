<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Proposals\Models\Proposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaasFoundation\Models\Activity;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class ProposalDetailTest extends TestCase
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

    public function test_the_proposal_detail_fragment_renders_an_editable_form_for_managers(): void
    {
        $alice = $this->user('alice@example.com');
        $proposal = Proposal::query()->where('company', 'Acme Corp')->firstOrFail();

        $this->actingAs($alice)
            ->get(route('deally.proposals.show', $proposal))
            ->assertOk()
            ->assertSee('Acme Enterprise v2')
            ->assertSee('Save Changes');
    }

    public function test_a_proposal_can_be_updated(): void
    {
        $alice = $this->user('alice@example.com');
        $proposal = Proposal::query()->where('company', 'Acme Corp')->firstOrFail();

        $this->actingAs($alice)
            ->put(route('deally.proposals.update', $proposal), [
                'name' => 'Acme Enterprise v3',
                'value' => 92000,
                'package' => 'Enterprise Suite — 700 users',
                'quote' => 'Quoted after the pricing review.',
                'status' => 'sent',
            ])
            ->assertSessionHas('toast');

        $proposal->refresh();

        $this->assertSame('Acme Enterprise v3', $proposal->name);
        $this->assertSame(92000.0, (float) $proposal->value);
        $this->assertSame('sent', $proposal->status);

        $this->assertTrue(
            Activity::query()
                ->where('subject_type', $proposal->getMorphClass())
                ->where('subject_id', $proposal->getKey())
                ->where('event', 'proposal.updated')
                ->exists()
        );
    }

    public function test_the_proposal_status_change_endpoint_is_logged(): void
    {
        $alice = $this->user('alice@example.com');
        $proposal = Proposal::query()->where('company', 'Acme Corp')->firstOrFail();

        $this->actingAs($alice)
            ->post(route('deally.proposals.status', $proposal), ['status' => 'approved'])
            ->assertSessionHas('toast');

        $this->assertSame('approved', $proposal->fresh()->status);
    }

    public function test_a_sales_agent_cannot_edit_another_owners_proposal(): void
    {
        $charlie = $this->user('charlie@example.com');
        $proposal = Proposal::query()->where('company', 'Acme Corp')->firstOrFail();

        $this->actingAs($charlie)
            ->put(route('deally.proposals.update', $proposal), [
                'name' => 'Acme Enterprise vX',
                'status' => 'draft',
            ])
            ->assertForbidden();
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
