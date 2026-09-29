<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Deally\Calls\Models\Call;
use Deally\Calls\Models\CallEphemeral;
use Deally\Calls\Models\CallFinding;
use Deally\Calls\Models\CallFlag;
use Deally\Calls\Models\CallInvitation;
use Deally\Calls\Models\MeetingPlatform;
use Deally\Calls\Models\TranscriptLine;
use Deally\Calls\Services\CallReviewBrief;
use Deally\Calls\Services\MeetingPlatformManager;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Core\Services\TenantConnectionBinder;
use Deally\Pipeline\Models\Customer;
use Deally\Proposals\Models\KnowledgeGap;
use Deally\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

/**
 * Call initiation, the review task, and the honesty guarantees around them.
 *
 * A large part of this file asserts that the system does *not* invent things.
 * Those assertions are the point: a fabricated join URL, a bot that reports it
 * joined when it did not, a customer panel of invented signals, and a readiness
 * chip that says "Warm — high intent" on every call all pass every ordinary
 * happy-path test while being the most damaging things the product can do.
 */
class CallInitiationAndReviewTest extends TestCase
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

        config([
            'services.openai.key' => 'test-key',
            'services.live_ai.driver' => 'openai',
            /* The invitation is real mail, so it is rendered for real against the
               log driver. A fake would prove the row was written without proving
               the message the customer receives is renderable at all. */
            'mail.default' => 'log',
        ]);

        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        foreach (glob(database_path('tenants').DIRECTORY_SEPARATOR.'*.sqlite') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /* ---------- helpers ---------- */

    private function alice(): User
    {
        return User::query()->where('email', 'alice@example.com')->firstOrFail();
    }

    private function zoom(): MeetingPlatform
    {
        return MeetingPlatform::query()->updateOrCreate(
            ['key' => 'zoom'],
            [
                'name' => 'Zoom',
                'icon' => '🎥',
                'sort_order' => 10,
                'enabled' => true,
                'connected' => false,
                'connection_note' => 'Not connected. DeAlly needs a Zoom Server-to-Server OAuth app.',
            ]
        );
    }

    private function analysisPayload(array $overrides = []): array
    {
        return [
            'choices' => [[
                'message' => ['content' => json_encode(array_merge([
                    'signals' => [],
                    'recommendations' => [],
                    'noticed' => null,
                    'proposal_intent' => false,
                    'proposal_intent_note' => '',
                ], $overrides))],
            ]],
        ];
    }

    private function chunkPayload(array $overrides = []): array
    {
        return array_merge([
            'audio' => UploadedFile::fake()->createWithContent('audio.webm', 'fake-audio-bytes'),
            'speaker' => 'customer',
            'chunk_id' => 'chunk-'.uniqid(),
            'client_sequence' => 0,
            'started_at_ms' => 1_700_000_000_000,
            'duration_ms' => 8000,
        ], $overrides);
    }

    /* ---------- platform picker and the flow around it ---------- */

    public function test_the_picker_offers_only_enabled_platforms(): void
    {
        $this->zoom();
        MeetingPlatform::query()->updateOrCreate(
            ['key' => 'disabled_one'],
            ['name' => 'Disabled', 'enabled' => false, 'connected' => false]
        );

        $available = app(MeetingPlatformManager::class)->available()->pluck('key');

        $this->assertTrue($available->contains('zoom'));
        $this->assertFalse($available->contains('disabled_one'), 'A disabled platform is never offered.');
    }

    public function test_every_seeded_platform_is_enabled_but_admits_no_bot(): void
    {
        $platforms = MeetingPlatform::query()->orderBy('sort_order')->get();

        $this->assertNotEmpty($platforms);

        foreach ($platforms as $platform) {
            $this->assertTrue($platform->enabled, "{$platform->key} should be offered.");
            $this->assertFalse($platform->connected, "{$platform->key} has no credentials, so it must not claim to be connected.");
            $this->assertNotEmpty($platform->connection_note, "{$platform->key} must say what connecting it needs.");
        }
    }

    public function test_scheduling_a_call_does_not_invent_a_join_url(): void
    {
        $this->zoom();

        $this->actingAs($this->alice())
            ->post(route('deally.calls.store'), [
                'name' => 'Discovery',
                'company' => 'Acme Corp',
                'date' => now()->toDateString(),
                'meeting_platform' => 'zoom',
            ])
            ->assertRedirect();

        $call = Call::query()->where('company', 'Acme Corp')->latest('id')->firstOrFail();

        $this->assertSame('zoom', $call->meeting_platform);
        $this->assertNull($call->meeting_external_id, 'No provider answered, so there is no meeting id.');
        $this->assertNull($call->meeting_join_url, 'No provider answered, so there is no join url.');
    }

    public function test_a_disabled_platform_cannot_be_attached_by_a_crafted_request(): void
    {
        MeetingPlatform::query()->updateOrCreate(
            ['key' => 'sneaky'],
            ['name' => 'Not enabled', 'enabled' => false, 'connected' => false]
        );

        $this->actingAs($this->alice())
            ->post(route('deally.calls.store'), [
                'name' => 'Discovery',
                'company' => 'Acme Corp',
                'meeting_platform' => 'sneaky',
            ]);

        $call = Call::query()->where('company', 'Acme Corp')->latest('id')->firstOrFail();

        $this->assertNull($call->meeting_platform);
    }

    public function test_scheduling_a_call_stores_the_customer_job_title_verbatim(): void
    {
        $this->actingAs($this->alice())
            ->post(route('deally.calls.store'), [
                'name' => 'Discovery',
                'company' => 'Acme Corp',
                'contact_role' => 'VP Engineering',
            ])
            ->assertRedirect();

        $call = Call::query()->where('company', 'Acme Corp')->latest('id')->firstOrFail();

        $this->assertSame('VP Engineering', $call->contact_role);
    }

    public function test_the_new_call_form_asks_for_the_customer_job_title_not_a_role(): void
    {
        $this->actingAs($this->alice())
            ->get(route('deally.calls.index'))
            ->assertOk()
            ->assertSee('Contact job title')
            ->assertSee('e.g. CTO, VP Engineering');
    }

    public function test_call_creation_offers_the_customer_picker_and_inline_create(): void
    {
        $this->actingAs($this->alice())
            ->get(route('deally.calls.index'))
            ->assertOk()
            ->assertSee('Pick a customer')
            ->assertSee('call-customer-select')
            ->assertSee('call-new-customer-toggle', false);
    }

    public function test_creating_a_call_for_an_existing_customer_uses_that_account(): void
    {
        $acme = Customer::query()->where('company', 'Acme Corp')->firstOrFail();
        $deal = $acme->opportunities()->firstOrFail();

        $this->actingAs($this->alice())
            ->post(route('deally.calls.store'), [
                'name' => 'Renewal check',
                'customer_id' => $acme->getKey(),
                'opportunity_id' => $deal->getKey(),
            ])
            ->assertRedirect();

        $call = Call::query()->where('name', 'Renewal check')->firstOrFail();

        $this->assertSame('Acme Corp', $call->company);
        $this->assertSame('Jane Doe', $call->contact_name);
        $this->assertSame('CTO', $call->contact_role);
        $this->assertSame((string) $deal->getKey(), (string) $call->opportunity_id);
    }

    public function test_creating_a_call_can_create_the_customer_inline(): void
    {
        $this->actingAs($this->alice())
            ->post(route('deally.calls.store'), [
                'name' => 'Onboarding kickoff',
                'new_customer_company' => 'Initech',
                'new_customer_contact_name' => 'Sam King',
                'new_customer_contact_title' => 'IT Lead',
            ])
            ->assertRedirect();

        $customer = Customer::query()->where('company', 'Initech')->firstOrFail();
        $call = Call::query()->where('name', 'Onboarding kickoff')->firstOrFail();

        $this->assertSame('Initech', $call->company);
        $this->assertSame('Sam King', $call->contact_name);
        $this->assertSame('IT Lead', $call->contact_role);
        $this->assertSame((string) auth()->id(), (string) $customer->owner_user_id);
    }

    public function test_an_opportunity_from_another_customer_is_dropped(): void
    {
        $acme = Customer::query()->where('company', 'Acme Corp')->firstOrFail();
        $globex = Customer::query()->where('company', 'Globex Inc')->firstOrFail();
        $globexDeal = $globex->opportunities()->firstOrFail();

        $this->actingAs($this->alice())
            ->post(route('deally.calls.store'), [
                'name' => 'Cross-link attempt',
                'customer_id' => $acme->getKey(),
                'opportunity_id' => $globexDeal->getKey(),
            ])
            ->assertRedirect();

        $call = Call::query()->where('name', 'Cross-link attempt')->firstOrFail();

        $this->assertSame('Acme Corp', $call->company);
        $this->assertNull($call->opportunity_id);
    }

    public function test_admitting_the_bot_reports_unavailable_rather_than_claiming_success(): void
    {
        $this->zoom();

        $call = Call::factory()->create(['meeting_platform' => 'zoom']);

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.join', $call))
            ->assertStatus(409)
            ->assertJsonPath('bot_join_status', Call::BOT_JOIN_UNAVAILABLE)
            ->assertJsonPath('ok', false);

        $call->refresh();

        $this->assertSame(Call::BOT_JOIN_UNAVAILABLE, $call->bot_join_status);
        $this->assertNotSame(Call::BOT_JOIN_JOINED, $call->bot_join_status);
        $this->assertStringContainsString('Zoom Server-to-Server', $call->bot_join_note);
    }

    public function test_joining_with_no_platform_chosen_says_there_is_nothing_to_join(): void
    {
        $call = Call::factory()->create(['meeting_platform' => null]);

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.join', $call))
            ->assertStatus(409)
            ->assertJsonPath('bot_join_status', Call::BOT_JOIN_UNAVAILABLE);

        $this->assertStringContainsString(
            'No meeting platform',
            $call->refresh()->bot_join_note
        );
    }

    /* ---------- the invitation ---------- */

    public function test_the_invitation_is_sent_and_stored_verbatim_without_a_transcription_notice(): void
    {
        $this->zoom();

        $this->actingAs($this->alice())
            ->post(route('deally.calls.store'), [
                'name' => 'Discovery',
                'company' => 'Acme Corp',
                'meeting_platform' => 'zoom',
                'invite_email' => 'jane@acme.test',
            ])
            ->assertRedirect();

        $call = Call::query()->where('company', 'Acme Corp')->latest('id')->firstOrFail();
        $invitation = $call->invitations()->sole();

        $this->assertSame(CallInvitation::STATUS_SENT, $invitation->status);
        $this->assertSame('jane@acme.test', $invitation->recipient_email);
        $this->assertNotNull($invitation->sent_at);

        /* The invitation must not promise that the call is recorded: the call
           is transcribed, but the customer is never told so in the invitation. */
        $this->assertStringNotContainsString('transcribed', $invitation->body);

        $call->refresh();
        $this->assertSame(Call::INVITATION_SENT, $call->invitation_status);
    }

    public function test_the_invitation_never_contains_a_join_link_when_none_was_created(): void
    {
        $this->zoom();

        $call = Call::factory()->create(['meeting_platform' => 'zoom']);

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.invite', $call), ['email' => 'jane@acme.test'])
            ->assertOk();

        $body = $call->invitations()->sole()->body;

        $this->assertStringNotContainsString('Join here:', $body);
        $this->assertStringNotContainsString('zoom.us', $body);
        $this->assertStringContainsString('Zoom', $body);
        $this->assertStringContainsString('joining details shortly', $body);
    }

    public function test_a_failed_delivery_is_recorded_rather_than_thrown(): void
    {
        Mail::extend('refusing', fn (): never => throw new RuntimeException('Connection refused by the mail server'));
        config([
            'mail.default' => 'refusing',
            'mail.mailers.refusing' => ['transport' => 'refusing'],
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.invite', $call), ['email' => 'jane@acme.test'])
            ->assertStatus(502)
            ->assertJsonPath('ok', false);

        $invitation = $call->invitations()->sole();

        $this->assertSame(CallInvitation::STATUS_FAILED, $invitation->status);
        $this->assertStringContainsString('Connection refused', (string) $invitation->delivery_error);

        $call->refresh();
        $this->assertSame(Call::INVITATION_FAILED, $call->invitation_status);
    }

    public function test_the_invitation_can_be_previewed_before_it_reaches_a_customer(): void
    {
        $call = Call::factory()->create(['meeting_platform' => 'zoom']);

        $this->actingAs($this->alice())
            ->getJson(route('deally.calls.invite.preview', $call))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['copy' => ['subject', 'body']]);
    }

    public function test_resending_keeps_every_attempt_rather_than_overwriting_the_first(): void
    {
        $call = Call::factory()->create();

        $this->actingAs($this->alice())->postJson(route('deally.calls.invite', $call), ['email' => 'jane@acme.test'])->assertOk();
        $this->actingAs($this->alice())->postJson(route('deally.calls.invite', $call), ['email' => 'jane@acme.test'])->assertOk();

        $this->assertSame(2, $call->invitations()->count());
    }

    /* ---------- unplanned calls ---------- */

    public function test_a_no_show_is_recorded_even_when_the_call_is_rescheduled(): void
    {
        $call = Call::factory()->create(['status' => Call::STATUS_SCHEDULED]);

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.fail', $call), [
                'status' => Call::STATUS_NO_SHOW,
                'note' => 'Nobody joined.',
                'reschedule' => now()->addDays(3)->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('rescheduled', true);

        /* The no-show is what the record has to keep — a reschedule moves the
           calendar, it does not make the missed call never happened. The call
           returns to `scheduled` because that is now the true state, with the
           attempt preserved underneath. */
        $this->assertDatabaseHas('calls', [
            'id' => $call->id,
            'status' => Call::STATUS_SCHEDULED,
            'ended_reason' => 'no_show',
            'failure_note' => 'Nobody joined.',
        ], 'deally');

        $this->assertDatabaseHas('tasks', [
            'call_id' => $call->id,
            'title' => 'Reschedule call — '.$call->company,
        ], 'deally');
    }

    public function test_a_call_that_went_nowhere_reports_itself_as_such(): void
    {
        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.fail', $call), ['status' => Call::STATUS_FAILED, 'note' => 'Platform refused.'])
            ->assertOk();

        $this->assertTrue($call->refresh()->wentUnattended());
    }

    /* ---------- the ephemeral stream ---------- */

    public function test_the_heard_card_is_the_models_paraphrase_and_is_kept_on_the_record(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'Your pricing looks higher than we can approve.']),
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload([
                'noticed' => 'weighing us against Cisco on support coverage',
            ])),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk()
            ->assertJsonPath('ephemerals.0.kind', CallEphemeral::KIND_HEARD)
            ->assertJsonPath('ephemerals.0.body', 'weighing us against Cisco on support coverage');

        /* The stream is live-only, but the content has to outlive its six
           seconds or the review cannot show what the AI was attending to. */
        $ephemeral = $call->ephemerals()->sole();

        $this->assertSame('Heard', $ephemeral->label);
        $this->assertSame('openai', $ephemeral->source);
        $this->assertNotNull($ephemeral->transcript_line_id);
    }

    public function test_a_quoted_or_overlong_paraphrase_is_reduced_to_a_summary(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'Tell me about support.']),
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload([
                'noticed' => '"The customer asked about support" and then went on to say a great many other things at length',
            ])),
        ]);

        $call = Call::factory()->create();

        $response = $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk();

        $body = $response->json('ephemerals.0.body');

        $this->assertStringNotContainsString('"', $body, 'A card labelled as a summary must not be a quote.');
        $this->assertLessThanOrEqual(12, count(preg_split('/\s+/', $body)));
        $this->assertStringStartsWith('The customer asked about support', $body);
    }

    public function test_a_silent_window_is_reported_with_the_same_keys_as_a_real_one(): void
    {
        // "Thank you." stays in HALLUCINATION_PHRASES, so it still reads as a
        // silent window; the backchannel "Mm-hmm." is genuine speech now.
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => ' Thank you. ']),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk()
            ->assertJsonPath('silent', true)
            ->assertJsonPath('transcript', null)
            ->assertJsonCount(0, 'ephemerals')
            ->assertJsonCount(0, 'findings');
    }

    public function test_no_heard_card_is_written_when_the_model_has_nothing_to_summarise(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'We should look at pricing next.']),
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload(['noticed' => ''])),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk()
            ->assertJsonCount(0, 'ephemerals');

        $this->assertSame(0, $call->ephemerals()->count());
    }

    public function test_an_empty_objection_is_logged_instead_of_rejected(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload()),
        ]);

        $call = Call::factory()->create();

        /* A rep who hears resistance and has no words for it is still reporting
           something real. This used to 422, so the objection was lost. */
        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.query', $call), ['text' => '', 'objection' => 1])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('knowledge_gaps', [
            'call_id' => $call->id,
            'type' => 'objection',
        ], 'deally');
    }

    public function test_asking_the_assistant_records_what_was_asked_and_the_answer(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions*' => Http::response([
                'choices' => [['message' => ['content' => 'The pilot runs fourteen days.']]],
            ]),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.query', $call), ['text' => 'How long is the pilot?'])
            ->assertOk()
            ->assertJsonPath('answer', 'The pilot runs fourteen days.');

        $this->assertSame(1, $call->queries()->count());

        $this->assertDatabaseHas('call_ephemerals', [
            'call_id' => $call->id,
            'kind' => CallEphemeral::KIND_ASKED,
        ], 'deally');
    }

    public function test_asking_with_no_text_is_refused_rather_than_recorded_as_empty(): void
    {
        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.query', $call), ['text' => ''])
            ->assertStatus(422)
            ->assertJsonPath('error', 'text_required');

        $this->assertSame(0, $call->queries()->count());
    }

    /* ---------- proposal intent ---------- */

    public function test_proposal_intent_detected_on_the_call_is_recorded(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'Send me a formal quote for 400 seats.']),
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload([
                'proposal_intent' => true,
                'proposal_intent_note' => 'formal quote for 400 seats',
            ])),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk();

        $call->refresh();

        $this->assertTrue($call->proposal_intent);
        $this->assertSame('formal quote for 400 seats', $call->proposal_intent_note);
    }

    public function test_a_proposal_is_refused_when_the_call_never_agreed_one(): void
    {
        $call = Call::factory()->create(['proposal_intent' => false]);

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.proposal', $call))
            ->assertStatus(409)
            ->assertJsonPath('error', 'no_proposal_intent');
    }

    public function test_an_agreed_proposal_becomes_a_task(): void
    {
        $call = Call::factory()->create(['proposal_intent' => true, 'proposal_intent_note' => 'quote for 400 seats']);

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.proposal', $call))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('tasks', [
            'call_id' => $call->id,
            'title' => 'Draft proposal — '.$call->company,
        ], 'deally');
    }

    public function test_the_review_offers_a_proposal_only_when_one_was_agreed(): void
    {
        $withoutIntent = Call::factory()->create(['proposal_intent' => false]);

        $this->actingAs($this->alice())
            ->get(route('deally.calls.review', $withoutIntent))
            ->assertOk()
            ->assertSee('No proposal was agreed on this call')
            ->assertDontSee('Detected intent:');

        $withIntent = Call::factory()->create(['proposal_intent' => true, 'proposal_intent_note' => 'a quote']);

        $this->actingAs($this->alice())
            ->get(route('deally.calls.review', $withIntent))
            ->assertOk()
            ->assertSee('Create Proposal')
            ->assertSee('Agreed on this call');
    }

    /* ---------- corrections ---------- */

    public function test_correcting_sentiment_keeps_the_ais_original_read(): void
    {
        $call = Call::factory()->create([
            'sentiment' => 'neutral',
            'ai_sentiment' => 'neutral',
        ]);

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.correct', $call), [
                'field' => 'sentiment',
                'value' => 'positive',
                'note' => 'They were keen once we got past procurement.',
            ])
            ->assertOk()
            ->assertJsonPath('sentiment', 'positive')
            ->assertJsonPath('correction.ai_value', 'neutral')
            ->assertJsonPath('correction.corrected_value', 'positive');

        $call->refresh();

        $this->assertSame('positive', $call->sentiment);
        $this->assertSame('neutral', $call->ai_sentiment, 'The model\'s own read must survive the correction.');
        $this->assertTrue($call->sentimentWasCorrected());
        $this->assertSame(1, $call->corrections()->count());
    }

    public function test_correcting_readiness_keeps_the_ais_original_read(): void
    {
        $call = Call::factory()->create([
            'readiness' => 'hot',
            'ai_readiness' => 'hot',
        ]);

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.correct', $call), ['field' => 'readiness', 'value' => 'cold'])
            ->assertOk()
            ->assertJsonPath('readiness', 'cold');

        $call->refresh();

        $this->assertSame('hot', $call->ai_readiness);
        $this->assertTrue($call->readinessWasCorrected());
    }

    public function test_the_review_shows_the_ais_read_alongside_the_correction(): void
    {
        $call = Call::factory()->create([
            'sentiment' => 'positive',
            'ai_sentiment' => 'negative',
            'readiness' => 'cold',
            'ai_readiness' => 'hot',
        ]);

        $html = $this->actingAs($this->alice())
            ->get(route('deally.calls.review', $call))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('corrected by you', $html);
        $this->assertStringContainsString('AI read:', $html);
    }

    /* ---------- deal-status flags and the close gate ---------- */

    public function test_a_deal_flag_is_raised_and_holds_the_review_task_open(): void
    {
        $call = Call::factory()->create();
        $task = Task::query()->create([
            'call_id' => $call->id,
            'title' => 'Review Call — '.$call->company,
            'linked_company' => $call->company,
            'owner_user_id' => $this->alice()->id,
            'status' => 'todo',
        ]);

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.flags.store', $call), [
                'headline' => 'No decision maker on the call',
                'rationale' => 'Only the procurement lead joined.',
            ])
            ->assertOk()
            ->assertJsonPath('flag.status', CallFlag::STATUS_OPEN);

        $task->refresh();

        $this->assertNotNull($task->blockedReason());

        $this->actingAs($this->alice())
            ->from(route('deally.tasks.index'))
            ->post(route('deally.tasks.toggle', $task))
            ->assertRedirect(route('deally.tasks.index'))
            ->assertSessionHas('error');

        $task->refresh();
        $this->assertNotSame('closed', $task->status, 'An unresolved risk must not vanish into a closed checklist.');
    }

    public function test_resolving_a_flag_unblocks_the_task(): void
    {
        $call = Call::factory()->create();
        $task = Task::query()->create([
            'call_id' => $call->id,
            'title' => 'Review Call — '.$call->company,
            'linked_company' => $call->company,
            'owner_user_id' => $this->alice()->id,
            'status' => 'todo',
        ]);

        $flag = CallFlag::factory()->create([
            'call_id' => $call->id,
            'status' => CallFlag::STATUS_OPEN,
        ]);

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.flags.resolve', ['call' => $call, 'flag' => $flag]), [
                'status' => CallFlag::STATUS_CONFIRMED,
                'resolution_note' => 'Moved to the CRO.',
            ])
            ->assertOk();

        $this->assertNull($task->refresh()->blockedReason());

        $this->actingAs($this->alice())
            ->from(route('deally.tasks.index'))
            ->post(route('deally.tasks.toggle', $task))
            ->assertSessionHas('toast');

        $this->assertSame('closed', $task->refresh()->status);
    }

    public function test_a_flag_on_another_call_does_not_block_this_one(): void
    {
        $call = Call::factory()->create();
        $otherCall = Call::factory()->create(['company' => $call->company]);

        CallFlag::factory()->create(['call_id' => $otherCall->id, 'status' => CallFlag::STATUS_OPEN]);

        $task = Task::query()->create([
            'call_id' => $call->id,
            'title' => 'Review Call — '.$call->company,
            'linked_company' => $call->company,
            'owner_user_id' => $this->alice()->id,
            'status' => 'todo',
        ]);

        $this->assertNull($task->blockedReason());
    }

    public function test_a_flag_from_another_call_cannot_be_resolved_through_this_one(): void
    {
        $call = Call::factory()->create();
        $otherCall = Call::factory()->create();
        $flag = CallFlag::factory()->create(['call_id' => $otherCall->id]);

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.flags.resolve', ['call' => $call, 'flag' => $flag]), ['status' => 'dismissed'])
            ->assertNotFound();

        $this->assertSame(CallFlag::STATUS_OPEN, $flag->refresh()->status);
    }

    public function test_the_task_list_marks_a_blocked_review_rather_than_only_failing_on_click(): void
    {
        $call = Call::factory()->create();
        Task::query()->create([
            'call_id' => $call->id,
            'title' => 'Review Call — '.$call->company,
            'linked_company' => $call->company,
            'owner_user_id' => $this->alice()->id,
            'status' => 'todo',
        ]);
        CallFlag::factory()->create(['call_id' => $call->id, 'status' => CallFlag::STATUS_OPEN]);

        $this->actingAs($this->alice())
            ->get(route('deally.tasks.index'))
            ->assertOk()
            ->assertSee('Needs a decision');
    }

    public function test_each_task_opens_the_review_for_its_own_call(): void
    {
        $oldCall = Call::factory()->create(['company' => 'Northwind Traders', 'date' => now()->subMonths(3)]);
        $newCall = Call::factory()->create(['company' => 'Northwind Traders', 'date' => now()]);

        $oldTask = Task::query()->create([
            'call_id' => $oldCall->id,
            'title' => 'Review Call — Northwind Traders',
            'linked_company' => 'Northwind Traders',
            'owner_user_id' => $this->alice()->id,
            'status' => 'closed',
        ]);

        $newTask = Task::query()->create([
            'call_id' => $newCall->id,
            'title' => 'Review Call — Northwind Traders',
            'linked_company' => 'Northwind Traders',
            'owner_user_id' => $this->alice()->id,
            'status' => 'todo',
        ]);

        $html = $this->actingAs($this->alice())
            ->get(route('deally.tasks.index'))
            ->assertOk()
            ->getContent();

        /* Both tasks carry a distinct modal URL, and each one's own call. Matching
           on the company name previously sent every task for a company to the
           most recent call's review, so reviewing an old call meant reading a new
           one. */
        $this->assertSame(
            route('deally.calls.review', $oldCall),
            $this->reviewUrlFor($html, $oldTask)
        );

        $this->assertSame(
            route('deally.calls.review', $newCall),
            $this->reviewUrlFor($html, $newTask)
        );
    }

    /**
     * The full-review link the list renders beside a task's own modal.
     */
    private function reviewUrlFor(string $html, Task $task): ?string
    {
        preg_match(
            '/data-modal-url="'.preg_quote(route('deally.tasks.show', $task), '/').'"\s+data-review-url="([^"]*)"/',
            $html,
            $matches
        );

        return $matches[1] ?? null;
    }

    /* ---------- missed objections ---------- */

    public function test_a_missed_objection_can_be_added_during_review(): void
    {
        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.objections.store', $call), [
                'text' => 'Worried about migrating off their legacy tool.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('knowledge_gaps', [
            'call_id' => $call->id,
            'type' => 'objection',
            'text' => 'Worried about migrating off their legacy tool.',
        ], 'deally');

        $this->assertDatabaseHas('call_ephemerals', [
            'call_id' => $call->id,
            'kind' => CallEphemeral::KIND_OBJECTION,
        ], 'deally');
    }

    public function test_a_missed_objection_cannot_be_attached_to_another_calls_line(): void
    {
        $call = Call::factory()->create();
        $otherCall = Call::factory()->create();
        $foreignLine = TranscriptLine::factory()->create(['call_id' => $otherCall->id]);

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.objections.store', $call), [
                'text' => 'Something',
                'transcript_line_id' => $foreignLine->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'line_not_in_call');

        $this->assertDatabaseMissing('knowledge_gaps', [
            'call_id' => $call->id,
        ], 'deally');
    }

    public function test_the_review_task_separates_detected_objections_from_ones_the_rep_added(): void
    {
        $call = Call::factory()->create();

        CallFinding::factory()->signal('objection')->create([
            'call_id' => $call->id,
            'body' => 'Pushed back on price.',
        ]);

        KnowledgeGap::query()->create([
            'call_id' => $call->id,
            'type' => 'objection',
            'text' => 'Migration risk the AI did not catch.',
            'status' => 'pending',
            'source' => 'review',
        ]);

        $brief = CallReviewBrief::forCalls([$call->id])->get($call->id);
        $log = $brief->objectionLog();

        $this->assertCount(2, $log);
        $this->assertFalse($log[0]['added_by_rep']);
        $this->assertTrue($log[1]['added_by_rep']);
    }

    public function test_outstanding_commitments_come_from_the_gap_log_not_the_transcript(): void
    {
        $call = Call::factory()->create();

        KnowledgeGap::query()->create([
            'call_id' => $call->id,
            'type' => 'gap',
            'text' => 'Security pack promised for Friday.',
            'status' => 'pending',
            'source' => 'call',
        ]);
        KnowledgeGap::query()->create([
            'call_id' => $call->id,
            'type' => 'gap',
            'text' => 'Already dealt with.',
            'status' => 'resolved',
            'source' => 'call',
        ]);

        $brief = CallReviewBrief::forCalls([$call->id])->get($call->id);
        $actions = $brief->missedActions();

        $this->assertCount(1, $actions);
        $this->assertSame('Security pack promised for Friday.', $actions[0]['text']);
    }

    /* ---------- review task modal ---------- */

    public function test_the_review_task_modal_renders_the_correction_surface(): void
    {
        $call = Call::factory()->create(['company' => 'Acme Corp', 'sentiment' => 'neutral', 'ai_sentiment' => 'neutral']);
        $task = Task::query()->create([
            'call_id' => $call->id,
            'title' => 'Review Call — '.$call->company,
            'linked_company' => $call->company,
            'owner_user_id' => $this->alice()->id,
            'status' => 'todo',
        ]);

        $this->actingAs($this->alice())
            ->get(route('deally.tasks.show', $task))
            ->assertOk()
            ->assertSee('How DeAlly read it')
            ->assertSee('Close readiness')
            ->assertSee('Still outstanding')
            ->assertSee('Objections')
            ->assertSee(route('deally.calls.correct', $call))
            ->assertSee(route('deally.calls.review', $call));
    }

    public function test_the_review_task_modal_says_when_there_is_no_call_to_review(): void
    {
        $task = Task::query()->create([
            'title' => 'Follow up with Acme',
            'linked_company' => 'Acme Corp',
            'owner_user_id' => $this->alice()->id,
            'status' => 'todo',
        ]);

        $this->actingAs($this->alice())
            ->get(route('deally.tasks.show', $task))
            ->assertOk()
            ->assertSee('not linked to a recorded call')
            ->assertDontSee('How DeAlly read it');
    }

    /* ---------- the heard record is actually read back ---------- */

    public function test_the_heard_cards_are_read_back_by_the_review_task(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'We are comparing you with Cisco.']),
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload([
                'noticed' => 'comparing us with Cisco on support',
            ])),
        ]);

        $call = Call::factory()->create(['company' => 'Acme Corp']);

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk();

        $task = Task::query()->create([
            'call_id' => $call->id,
            'title' => 'Review Call — '.$call->company,
            'linked_company' => $call->company,
            'owner_user_id' => $this->alice()->id,
            'status' => 'todo',
        ]);

        /* The stream fades each card after a few seconds, so if nothing reads
           the rows back the "kept on the record" guarantee is hollow. */
        $this->actingAs($this->alice())
            ->get(route('deally.tasks.show', $task))
            ->assertOk()
            ->assertSee('What DeAlly heard')
            ->assertSee('comparing us with Cisco on support');
    }

    public function test_the_review_page_reads_the_heard_cards_back_too(): void
    {
        $call = Call::factory()->create(['company' => 'Acme Corp']);

        CallEphemeral::query()->create([
            'call_id' => $call->id,
            'kind' => CallEphemeral::KIND_HEARD,
            'label' => 'Heard',
            'body' => 'budget was cut this quarter',
            'source' => 'openai',
        ]);

        $this->actingAs($this->alice())
            ->get(route('deally.calls.review', $call))
            ->assertOk()
            ->assertSee('What DeAlly heard')
            ->assertSee('budget was cut this quarter');
    }

    public function test_the_review_says_when_nothing_was_heard_rather_than_showing_a_blank_list(): void
    {
        $call = Call::factory()->create(['company' => 'Acme Corp']);

        $this->actingAs($this->alice())
            ->get(route('deally.calls.review', $call))
            ->assertOk()
            ->assertSee('What DeAlly heard')
            ->assertSee('Nothing was paraphrased on this call');
    }

    public function test_other_ephemeral_kinds_do_not_leak_into_the_heard_list(): void
    {
        $call = Call::factory()->create(['company' => 'Acme Corp']);

        CallEphemeral::query()->create([
            'call_id' => $call->id,
            'kind' => CallEphemeral::KIND_OBJECTION,
            'label' => 'Objection',
            'body' => 'customer said the price is too high',
            'source' => 'Rep · review',
        ]);

        $this->actingAs($this->alice())
            ->get(route('deally.calls.review', $call))
            ->assertOk()
            ->assertSee('Nothing was paraphrased on this call');
    }

    public function test_the_summary_page_opens_the_task_itself_rather_than_the_task_list(): void
    {
        $call = Call::factory()->create(['company' => 'Acme Corp']);
        $task = Task::query()->create([
            'call_id' => $call->id,
            'title' => 'Review Call — '.$call->company,
            'linked_company' => $call->company,
            'owner_user_id' => $this->alice()->id,
            'status' => 'todo',
        ]);

        $this->actingAs($this->alice())
            ->get(route('deally.calls.summary', $call))
            ->assertOk()
            ->assertSee(route('deally.tasks.show', $task))
            /* The call layout must render the pushed modal shell, or the
               "View task" button has nothing to open. */
            ->assertSee('id="modal-task"', false);
    }

    /* ---------- post-call summary honesty ---------- */

    public function test_the_summary_reports_the_real_readiness_not_a_fixed_warm_label(): void
    {
        $cold = Call::factory()->create(['readiness' => 'cold', 'ai_readiness' => 'cold']);

        $this->actingAs($this->alice())
            ->get(route('deally.calls.summary', $cold))
            ->assertOk()
            ->assertSee('Cold')
            ->assertDontSee('Warm — high intent');
    }

    public function test_the_summary_surfaces_an_unresolved_flag(): void
    {
        $call = Call::factory()->create();
        CallFlag::factory()->create([
            'call_id' => $call->id,
            'status' => CallFlag::STATUS_OPEN,
            'headline' => 'Champion has left the company',
        ]);

        $this->actingAs($this->alice())
            ->get(route('deally.calls.summary', $call))
            ->assertOk()
            ->assertSee('One deal-status flag')
            ->assertSee('Champion has left the company')
            ->assertSee('cannot be closed');
    }

    /* ---------- call detail ---------- */

    public function test_the_call_detail_fragment_leads_with_the_summary_and_carries_the_findings(): void
    {
        $call = Call::factory()->create(['summary' => 'Agreed a pilot for the eastern region.']);

        CallFinding::factory()->create([
            'call_id' => $call->id,
            'body' => 'Asked about implementation timeline.',
        ]);

        $html = $this->actingAs($this->alice())
            ->get(route('deally.calls.detail', $call))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Agreed a pilot for the eastern region.', $html);
        $this->assertStringContainsString('What DeAlly picked up', $html);
        $this->assertStringContainsString('Asked about implementation timeline.', $html);

        /* The outcome has to come first: it used to sit below the whole
           transcript, so finding it meant scrolling past the conversation. */
        $this->assertLessThan(
            strpos($html, 'Transcript'),
            strpos($html, 'Agreed a pilot for the eastern region.')
        );
    }

    public function test_the_call_detail_explains_an_empty_findings_list(): void
    {
        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->get(route('deally.calls.detail', $call))
            ->assertOk()
            ->assertSee('No findings were recorded for this call');
    }

    public function test_the_calls_list_opens_the_detail_as_a_modal(): void
    {
        $call = Call::factory()->create(['company' => 'Acme Corp']);

        $html = $this->actingAs($this->alice())
            ->get(route('deally.calls.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-modal-url="'.route('deally.calls.detail', $call).'"', $html);
        $this->assertStringContainsString('modal-call-detail', $html);
    }

    /* ---------- the live call panel ---------- */

    public function test_the_customer_panel_shows_real_context_and_no_invented_signals(): void
    {
        /* A company the seeder does not touch, so "first call" is a fact about
           the record rather than an accident of the fixture. */
        $call = Call::factory()->create(['company' => 'Northwind Traders']);

        $this->actingAs($this->alice())
            ->get(route('deally.calls.live', $call))
            ->assertOk()
            ->assertSee('This is the first recorded call with Northwind Traders.')
            ->assertDontSee('Budget mentioned 4 times')
            ->assertDontSee('Sentiment trending positive over last 3 calls')
            ->assertDontSee('spec sheet promised, not sent');
    }

    public function test_the_customer_panel_reports_a_real_sentiment_trend_from_real_calls(): void
    {
        Call::factory()->create([
            'company' => 'Northwind Traders',
            'status' => Call::STATUS_COMPLETED,
            'sentiment' => 'negative',
            'date' => now()->subMonth(),
        ]);
        Call::factory()->create([
            'company' => 'Northwind Traders',
            'status' => Call::STATUS_COMPLETED,
            'sentiment' => 'neutral',
            'date' => now()->subWeeks(2),
        ]);

        $current = Call::factory()->create(['company' => 'Northwind Traders']);

        $this->actingAs($this->alice())
            ->get(route('deally.calls.live', $current))
            ->assertOk()
            ->assertSee('Sentiment improving across the last 2 calls');
    }

    public function test_a_single_previous_call_is_not_called_a_trend(): void
    {
        Call::factory()->create([
            'company' => 'Northwind Traders',
            'status' => Call::STATUS_COMPLETED,
            'sentiment' => 'negative',
            'date' => now()->subMonth(),
        ]);

        $current = Call::factory()->create(['company' => 'Northwind Traders']);

        $this->actingAs($this->alice())
            ->get(route('deally.calls.live', $current))
            ->assertOk()
            ->assertDontSee('Sentiment improving across the last')
            ->assertDontSee('Sentiment softening across the last');
    }

    public function test_the_live_header_states_the_platform_rather_than_implying_a_bot_joined(): void
    {
        $this->zoom();

        $call = Call::factory()->create(['meeting_platform' => 'zoom']);

        $this->actingAs($this->alice())
            ->get(route('deally.calls.live', $call))
            ->assertOk()
            ->assertSee('bot not admitted');
    }
}
