<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Calls\Models\Call;
use Deally\Calls\Models\CallFinding;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Core\Services\TenantConnectionBinder;
use Deally\Proposals\Models\KnowledgeEntry;
use Deally\Proposals\Models\KnowledgeGap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

/**
 * Unresolved knowledge gaps are surfaced to the keyed queue.
 *
 * A freshly created gap notifies the members who can act on the Solutions
 * queue (solutions-lead/owner/admin with an active membership in the tenant).
 * The notification is flagged unresolved and is deleted the moment the gap
 * leaves the queue — approved or rejected — while an edit that keeps it
 * pending leaves it in the inbox.
 */
class KnowledgeGapNotificationTest extends TestCase
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

        $membership = $this->user('david@example.com')->memberships()->active()->with('tenant')->first();

        app('db')->purge('deally');
        app(TenantConnectionBinder::class)->bind($membership->tenant);

        Http::fake();
    }

    protected function tearDown(): void
    {
        foreach (glob(database_path('tenants').DIRECTORY_SEPARATOR.'*.sqlite') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /* ---------- helpers ---------- */

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function acmeId(): string
    {
        return Tenant::query()->where('slug', 'acme-corp')->firstOrFail()->id;
    }

    private function gapNotifications(User $user, int $gapId)
    {
        return $user->notifications()
            ->where('type', 'gap')
            ->get()
            ->filter(fn ($notification) => ($notification->data['gap_id'] ?? null) === $gapId);
    }

    /**
     * Drives the real recordGap path through the unhelpful-feedback route.
     */
    private function createGapViaFeedback(): KnowledgeGap
    {
        $call = Call::factory()->create();
        $finding = CallFinding::factory()->create(['call_id' => $call->id]);

        $this->actingAs($this->user('alice@example.com'))
            ->postJson(route('deally.calls.live.finding.feedback', ['call' => $call, 'finding' => $finding]), [
                'status' => 'unhelpful',
            ])
            ->assertOk();

        return KnowledgeGap::query()->where('call_finding_id', $finding->id)->firstOrFail();
    }

    /* ---------- creating a gap notifies the queue ---------- */

    public function test_a_new_gap_notifies_queue_members_but_no_other_roles(): void
    {
        $gap = $this->createGapViaFeedback();

        foreach (['alice@example.com', 'bob@example.com', 'david@example.com'] as $email) {
            $notifications = $this->gapNotifications($this->user($email), $gap->id);
            $this->assertCount(1, $notifications, "{$email} should get one unresolved notification.");

            $notification = $notifications->first();

            $this->assertSame('Unanswered question', $notification->data['title']);
            $this->assertSame($gap->text, $notification->data['body']);
            $this->assertSame(route('deally.solutions.index'), $notification->data['url']);
            $this->assertSame('question', $notification->data['type']);
            $this->assertTrue($notification->data['unresolved']);
            $this->assertSame($gap->id, $notification->data['gap_id']);
            $this->assertSame($this->acmeId(), $notification->data['tenant_id']);
        }

        $this->assertCount(0, $this->gapNotifications($this->user('charlie@example.com'), $gap->id));
    }

    public function test_repeated_feedback_on_the_same_finding_does_not_double_notify(): void
    {
        $call = Call::factory()->create();
        $finding = CallFinding::factory()->create(['call_id' => $call->id]);
        $url = route('deally.calls.live.finding.feedback', ['call' => $call, 'finding' => $finding]);

        $this->actingAs($this->user('alice@example.com'))
            ->postJson($url, ['status' => 'unhelpful'])
            ->assertOk();

        $this->actingAs($this->user('alice@example.com'))
            ->postJson($url, ['status' => 'unhelpful'])
            ->assertOk();

        $gap = KnowledgeGap::query()->where('call_finding_id', $finding->id)->firstOrFail();

        foreach (['alice@example.com', 'bob@example.com', 'david@example.com'] as $email) {
            $this->assertCount(1, $this->gapNotifications($this->user($email), $gap->id));
        }
    }

    /* ---------- resolving the gap clears the notifications ---------- */

    public function test_approving_a_gap_removes_its_unresolved_notifications(): void
    {
        $gap = $this->createGapViaFeedback();

        $this->assertCount(1, $this->gapNotifications($this->user('david@example.com'), $gap->id));

        $this->actingAs($this->user('david@example.com'))
            ->post(route('deally.solutions.gaps.resolve', $gap), [
                'action' => 'approve',
                'type' => 'product',
                'answer' => 'Enterprise includes 500+ seats, SSO and Slack.',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertSame('live', $gap->refresh()->status);

        foreach (['alice@example.com', 'bob@example.com', 'david@example.com'] as $email) {
            $this->assertCount(0, $this->gapNotifications($this->user($email), $gap->id));
        }
    }

    public function test_rejecting_a_gap_removes_its_unresolved_notifications(): void
    {
        $gap = $this->createGapViaFeedback();

        $this->actingAs($this->user('david@example.com'))
            ->post(route('deally.solutions.gaps.resolve', $gap), [
                'action' => 'reject',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertSame('rejected', $gap->refresh()->status);

        foreach (['alice@example.com', 'bob@example.com', 'david@example.com'] as $email) {
            $this->assertCount(0, $this->gapNotifications($this->user($email), $gap->id));
        }
    }

    public function test_editing_a_gap_keeps_its_unresolved_notifications(): void
    {
        $gap = $this->createGapViaFeedback();

        $this->actingAs($this->user('david@example.com'))
            ->post(route('deally.solutions.gaps.resolve', $gap), [
                'action' => 'edit',
                'text' => 'Reworded question',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertSame('pending', $gap->refresh()->status);

        foreach (['alice@example.com', 'bob@example.com', 'david@example.com'] as $email) {
            $this->assertCount(1, $this->gapNotifications($this->user($email), $gap->id));
        }
    }

    /* ---------- a question already in the queue is not re-added ---------- */

    public function test_logging_the_same_objection_twice_adds_a_single_queue_item(): void
    {
        $call = Call::factory()->create();
        $url = route('deally.calls.objections.store', $call);

        $first = $this->actingAs($this->user('alice@example.com'))
            ->postJson($url, ['text' => 'Worried about migrating off their legacy tool.'])
            ->assertOk();

        $second = $this->actingAs($this->user('alice@example.com'))
            ->postJson($url, ['text' => 'Worried about migrating off their legacy tool.'])
            ->assertOk();

        $this->assertSame($first->json('gap_id'), $second->json('gap_id'));

        $this->assertSame(1, KnowledgeGap::query()
            ->where('status', 'pending')
            ->where('type', 'objection')
            ->where('text', 'Worried about migrating off their legacy tool.')
            ->count(), 're-adding the same objection must reuse the queue item');

        $gap = KnowledgeGap::query()->findOrFail($first->json('gap_id'));

        foreach (['alice@example.com', 'bob@example.com', 'david@example.com'] as $email) {
            $this->assertCount(1, $this->gapNotifications($this->user($email), $gap->id));
        }
    }

    public function test_differently_worded_questions_each_get_their_own_queue_item(): void
    {
        $call = Call::factory()->create();
        $url = route('deally.calls.objections.store', $call);

        $this->actingAs($this->user('alice@example.com'))
            ->postJson($url, ['text' => 'Worried about migrating off their legacy tool.'])
            ->assertOk();

        $this->actingAs($this->user('alice@example.com'))
            ->postJson($url, ['text' => 'Is the SLA negotiable?'])
            ->assertOk();

        $this->assertSame(2, KnowledgeGap::query()
            ->where('status', 'pending')
            ->where('type', 'objection')
            ->count());
    }

    public function test_the_same_question_from_a_second_call_is_not_re_added(): void
    {
        $question = 'Do you support HIPAA?';

        foreach ([1, 2] as $_) {
            $call = Call::factory()->create();
            $finding = CallFinding::factory()->create(['call_id' => $call->id, 'body' => $question]);

            $this->actingAs($this->user('alice@example.com'))
                ->postJson(route('deally.calls.live.finding.feedback', ['call' => $call, 'finding' => $finding]), [
                    'status' => 'unhelpful',
                ])
                ->assertOk();
        }

        $gap = KnowledgeGap::query()->where('status', 'pending')->where('text', $question)->sole();

        foreach (['alice@example.com', 'bob@example.com', 'david@example.com'] as $email) {
            $this->assertCount(1, $this->gapNotifications($this->user($email), $gap->id));
        }
    }

    public function test_resolving_a_question_retires_its_duplicate_queue_items(): void
    {
        $gap = $this->createGapViaFeedback();

        // Simulate the pre-dedupe backlog: the same question standing in the
        // queue twice before gap creation became text-aware.
        $duplicate = KnowledgeGap::query()->create([
            'type' => $gap->type,
            'text' => $gap->text,
            'source' => 'Acme Demo · Re-surfaced question',
            'status' => 'pending',
        ]);

        $this->actingAs($this->user('david@example.com'))
            ->post(route('deally.solutions.gaps.resolve', $gap), [
                'action' => 'approve',
                'type' => 'product',
                'answer' => 'Yes — HIPAA compliance is included.',
            ])
            ->assertRedirect();

        $this->assertSame('live', $gap->refresh()->status);
        $this->assertSame('resolved', $duplicate->refresh()->status);

        // One answer written once — approving the duplicate copy must not
        // create a second, identical knowledge base entry.
        $this->assertSame(1, KnowledgeEntry::query()->where('title', $gap->text)->count());

        foreach (['alice@example.com', 'bob@example.com', 'david@example.com'] as $email) {
            $this->assertCount(0, $this->gapNotifications($this->user($email), $duplicate->id));
        }
    }

    public function test_a_question_reappearing_after_resolution_is_added_again(): void
    {
        $gap = $this->createGapViaFeedback();

        $this->actingAs($this->user('david@example.com'))
            ->post(route('deally.solutions.gaps.resolve', $gap), [
                'action' => 'approve',
                'type' => 'product',
                'answer' => 'Yes — HIPAA compliance is included.',
            ])
            ->assertRedirect();

        $this->assertSame('live', $gap->refresh()->status);

        // The same question resurfacing after the first copy was resolved is a
        // genuine recurrence, so it becomes a fresh pending queue item again.
        $call = Call::factory()->create();
        $finding = CallFinding::factory()->create(['call_id' => $call->id, 'body' => $gap->text]);

        $this->actingAs($this->user('alice@example.com'))
            ->postJson(route('deally.calls.live.finding.feedback', ['call' => $call, 'finding' => $finding]), [
                'status' => 'unhelpful',
            ])
            ->assertOk();

        $this->assertSame(1, KnowledgeGap::query()
            ->where('status', 'pending')
            ->where('text', $gap->text)
            ->count());

        $this->assertSame(2, KnowledgeGap::query()->where('text', $gap->text)->count());
    }

    /* ---------- the Expert Answers nav badge ---------- */

    public function test_expert_answers_nav_badge_shows_the_pending_gap_count(): void
    {
        $this->createGapViaFeedback();

        $pending = KnowledgeGap::query()->where('status', 'pending')->count();

        $this->actingAs($this->user('david@example.com'))
            ->get(route('deally.solutions.index'))
            ->assertOk()
            ->assertSee('Expert Answers <span class="nav-badge alert">'.$pending.'</span>', false);
    }

    /* ---------- notification categories are set at the company level ---------- */

    public function test_when_the_company_turns_off_expert_answers_no_queue_member_is_notified(): void
    {
        $tenant = Tenant::query()->where('slug', 'acme-corp')->firstOrFail();
        $settings = $tenant->settings ?? [];
        $settings['notifications'] = ['expert_gaps' => false, 'call_reports' => true];
        $tenant->update(['settings' => $settings]);

        $gap = $this->createGapViaFeedback();

        // The gap is still recorded for the queue itself...
        $this->assertSame('pending', $gap->refresh()->status);

        // ...but no notification is emitted anywhere in the company.
        foreach (['alice@example.com', 'bob@example.com', 'david@example.com'] as $email) {
            $this->assertCount(0, $this->gapNotifications($this->user($email), $gap->id));
        }
    }
}
