<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Core\Services\TenantConnectionBinder;
use Deally\Proposals\Models\KnowledgeEntry;
use Deally\Proposals\Models\KnowledgeGap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

/**
 * The Solutions Lead gap queue.
 *
 * Approving a gap has to write the answer where the AI can actually use it —
 * a gap that is only marked "live" never becomes something the assistant can
 * answer from, so the queue would be a list of misses that stays a list.
 */
class SolutionsGapResolutionTest extends TestCase
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

        $membership = $this->solutionsLead()->memberships()->active()->with('tenant')->first();

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

    /* ---------- helpers ---------- */

    private function solutionsLead(): User
    {
        return User::query()->where('email', 'david@example.com')->firstOrFail();
    }

    private function pendingGap(string $text = 'What does the Enterprise plan include?'): KnowledgeGap
    {
        return KnowledgeGap::query()->create([
            'type' => 'gap',
            'text' => $text,
            'source' => 'Acme Demo · Unanswered gap',
            'status' => 'pending',
        ]);
    }

    private function entryCount(): int
    {
        return KnowledgeEntry::query()->count();
    }

    /* ---------- the gap queue ---------- */

    public function test_approving_a_gap_writes_the_answer_into_the_knowledge_base(): void
    {
        $gap = $this->pendingGap();
        $before = $this->entryCount();

        $this->actingAs($this->solutionsLead())
            ->post(route('deally.solutions.gaps.resolve', $gap), [
                'action' => 'approve',
                'type' => 'product',
                'answer' => 'Enterprise includes 500+ seats, SSO and a Slack integration.',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $entry = KnowledgeEntry::query()->where('title', $gap->text)->sole();

        $this->assertSame($before + 1, $this->entryCount());
        $this->assertSame('product', $entry->type);
        $this->assertSame('Enterprise includes 500+ seats, SSO and a Slack integration.', $entry->description);
        $this->assertSame('live', $gap->refresh()->status);
    }

    public function test_approving_without_an_answer_creates_no_knowledge_entry(): void
    {
        $gap = $this->pendingGap();
        $before = $this->entryCount();

        $this->actingAs($this->solutionsLead())
            ->post(route('deally.solutions.gaps.resolve', $gap), [
                'action' => 'approve',
                'type' => 'pricing',
            ])
            ->assertSessionHasErrors('answer');

        $this->assertSame($before, $this->entryCount());
        $this->assertSame('pending', $gap->refresh()->status);
    }

    public function test_rejecting_a_gap_discards_it_without_touching_the_knowledge_base(): void
    {
        $gap = $this->pendingGap();
        $before = $this->entryCount();

        $this->actingAs($this->solutionsLead())
            ->post(route('deally.solutions.gaps.resolve', $gap), ['action' => 'reject'])
            ->assertRedirect();

        $this->assertSame('rejected', $gap->refresh()->status);
        $this->assertSame($before, $this->entryCount());
    }
}
