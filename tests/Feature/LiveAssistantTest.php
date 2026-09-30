<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Deally\Calls\Models\Call;
use Deally\Calls\Models\CallFinding;
use Deally\Calls\Models\CallRecording;
use Deally\Calls\Services\LiveAssistant;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Core\Services\TenantConnectionBinder;
use Deally\Proposals\Models\KnowledgeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use SaasFoundation\Models\Activity;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class LiveAssistantTest extends TestCase
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

        config(['services.openai.key' => 'test-key', 'services.live_ai.driver' => 'openai']);

        // Capture now keeps its audio, and a test run must not leave call
        // recordings in the real private disk.
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

    private function analysisPayload(array $overrides = []): array
    {
        return [
            'choices' => [[
                'message' => ['content' => json_encode(array_merge([
                    'signals' => [[
                        'kind' => 'objection',
                        'text' => 'Customer pushed back on price.',
                        'quote' => 'Your pricing looks higher than we can approve.',
                        'confidence' => 0.91,
                        'source_line' => null,
                    ]],
                    'recommendations' => [[
                        'role' => 'objection',
                        'label' => 'Objection · Reply',
                        'body' => 'Offer the two-week pilot at a reduced rate.',
                        'package' => 'Pilot program',
                        'source' => 'Knowledge base · pricing',
                        'confidence' => 0.88,
                        'source_line' => null,
                    ]],
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

    private function alice(): User
    {
        return User::query()->where('email', 'alice@example.com')->firstOrFail();
    }

    /* ---------- dual stream capture ---------- */

    public function test_customer_chunk_persists_transcript_and_findings(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'Your pricing looks higher than we can approve.']),
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload()),
        ]);

        $call = Call::factory()->create(['company' => 'Acme Corp']);

        $response = $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload());

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('transcript', 'Your pricing looks higher than we can approve.')
            ->assertJsonPath('line.speaker', 'customer')
            ->assertJsonPath('line.is_agent', false)
            ->assertJsonCount(2, 'findings');

        $this->assertDatabaseHas('transcript_lines', [
            'call_id' => $call->id,
            'speaker' => 'customer',
            'is_agent' => false,
            'text' => 'Your pricing looks higher than we can approve.',
            'duration_ms' => 8000,
            'started_at_ms' => 1_700_000_000_000,
            'is_final' => false,
            'provider' => 'openai',
        ], 'deally');

        $line = $call->transcriptLines()->sole();
        $finding = CallFinding::query()->firstOrFail();

        $this->assertSame('signal', $finding->type);
        $this->assertSame('objection', $finding->kind);
        $this->assertSame($line->id, $finding->transcript_line_id);
        $this->assertSame('openai', $finding->provider);
        $this->assertSame(2, $call->findings()->count());
    }

    public function test_agent_chunk_is_labelled_and_analysed(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'Understood, let me pull up our pilot terms.']),
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload()),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload([
                'speaker' => 'agent',
                'client_sequence' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('line.speaker', 'agent')
            ->assertJsonPath('line.is_agent', true)
            ->assertJsonCount(2, 'findings');

        $this->assertDatabaseHas('transcript_lines', [
            'call_id' => $call->id,
            'speaker' => 'agent',
            'is_agent' => true,
        ], 'deally');

        /* Analysis used to be skipped for the agent's own lines, so a rep
           testing into their microphone got a transcript and an always-empty
           shelf — indistinguishable from a provider failure. */
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'chat/completions'));
    }

    public function test_sequences_are_allocated_per_speaker_in_order(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::sequence()
                ->push(['text' => 'First customer line.'])
                ->push(['text' => 'First agent line.']),
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload()),
        ]);

        $call = Call::factory()->create();
        $user = $this->alice();

        $this->actingAs($user)->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload());
        $this->actingAs($user)->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload([
            'speaker' => 'agent',
            'client_sequence' => 1,
        ]));

        $this->assertSame([1, 2], $call->transcriptLines()->pluck('sequence')->all());
        $this->assertSame(['customer', 'agent'], $call->transcriptLines()->pluck('speaker')->all());
    }

    public function test_repeated_chunk_id_is_idempotent(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'We need SSO for security.']),
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload()),
        ]);

        $call = Call::factory()->create();
        $payload = $this->chunkPayload(['chunk_id' => 'stable-chunk-id']);

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $payload)
            ->assertOk();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('transcript', 'We need SSO for security.');

        $this->assertSame(1, $call->transcriptLines()->count());

        // A retry must not re-bill the analysis call either.
        Http::assertSentCount(2);
    }

    public function test_chunk_ids_are_scoped_to_their_own_call(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'Shared chunk text.']),
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload()),
        ]);

        $call = Call::factory()->create();
        $other = Call::factory()->create();
        $user = $this->alice();

        // Identical chunk ids on two calls must not collide: idempotency is
        // per call, not global.
        $this->actingAs($user)
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload(['chunk_id' => 'same-id']))
            ->assertOk()
            ->assertJsonPath('duplicate', false);

        $this->actingAs($user)
            ->postJson(route('deally.calls.live.transcribe', $other), $this->chunkPayload(['chunk_id' => 'same-id']))
            ->assertOk()
            ->assertJsonPath('duplicate', false);

        $this->assertSame(1, $call->transcriptLines()->count());
        $this->assertSame(1, $other->transcriptLines()->count());
    }

    public function test_chunk_metadata_is_required(): void
    {
        Http::fake();

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), [
                'audio' => UploadedFile::fake()->createWithContent('audio.webm', 'bytes'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['speaker', 'chunk_id', 'client_sequence']);

        Http::assertNothingSent();
    }

    /* ---------- failure handling ---------- */

    public function test_silence_is_not_treated_as_an_error(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => '   ']),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk()
            ->assertJsonPath('silent', true)
            ->assertJsonPath('transcript', null);

        $this->assertSame(0, $call->transcriptLines()->count());
    }

    public function test_hallucinated_filler_is_treated_as_silence(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => ' Thank you. ']),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk()
            ->assertJsonPath('silent', true)
            ->assertJsonPath('transcript', null);

        $this->assertSame(0, $call->transcriptLines()->count());

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/audio/transcriptions')) {
                return false;
            }

            // Greedy decoding plus an explicit language. A `prompt` is
            // deliberately absent: Whisper echoes a domain prior back as
            // transcript text, which put "B2B sales." into a real call.
            $body = (string) $request->body();

            return str_contains($body, 'temperature')
                && str_contains($body, 'language')
                && ! str_contains($body, 'prompt');
        });
    }

    public function test_a_configured_ca_bundle_is_used_to_verify_the_provider(): void
    {
        $bundle = tempnam(sys_get_temp_dir(), 'deally-ca-').'.pem';
        file_put_contents($bundle, "# test bundle\n");

        try {
            config(['services.openai.ca_bundle' => $bundle]);

            $assistant = new LiveAssistant((array) config('services.openai'));
            $method = new ReflectionMethod($assistant, 'caBundle');
            $method->setAccessible(true);

            // Windows cURL has no trust store of its own, so verification has to
            // be told where the certificates are rather than relying on the ini
            // of whichever SAPI happens to serve the app.
            $this->assertSame($bundle, $method->invoke($assistant));
        } finally {
            @unlink($bundle);
        }
    }

    public function test_an_unreadable_ca_bundle_is_reported_and_does_not_break_the_call(): void
    {
        Log::spy();

        config(['services.openai.ca_bundle' => 'C:\\not\\a\\real\\bundle.pem']);

        $assistant = new LiveAssistant((array) config('services.openai'));
        $method = new ReflectionMethod($assistant, 'caBundle');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($assistant));

        // Silently ignoring an unreadable bundle would restore the exact TLS
        // failure the setting exists to prevent.
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => str_contains($message, 'CA bundle'));
    }

    public function test_a_provider_transport_failure_records_why_it_failed(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 60: unable to get local issuer certificate'));

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertStatus(503)
            ->assertJsonPath('error', 'transcription_unavailable')
            // The response stays generic: the transport cause is for the log only.
            ->assertJsonMissing(['reason' => 'cURL error 60: unable to get local issuer certificate']);

        $logged = Activity::query()
            ->where('event', 'call.provider_failed')
            ->latest('id')
            ->first();

        $this->assertNotNull($logged, 'The provider failure was not recorded.');

        $properties = is_array($logged->properties)
            ? $logged->properties
            : json_decode((string) $logged->properties, true);

        // A null status means no HTTP response arrived, so the message is the
        // only thing that distinguishes a TLS trust problem from an outage.
        $this->assertNull($properties['status']);
        $this->assertStringContainsString('unable to get local issuer certificate', $properties['reason']);
    }

    public function test_a_lone_real_word_is_stored_as_a_transcript_line(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => ' The']),
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload()),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk()
            ->assertJsonPath('transcript', 'The');

        // The bar for speech is one word: punctuation, digits, and stock
        // phrases are still noise, but a single real word is an utterance.
        $this->assertSame('The', $call->transcriptLines()->sole()->text);
    }

    public function test_a_backchannel_acknowledgement_is_stored_as_a_transcript_line(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'Mm-hmm.']),
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload()),
        ]);

        $call = Call::factory()->create();

        // "Mm-hmm." is a real acknowledgement a rep should read back, not a
        // stock phrase Whisper emits for audio that carries no speech.
        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk()
            ->assertJsonPath('transcript', 'Mm-hmm.');

        $this->assertSame('Mm-hmm.', $call->transcriptLines()->sole()->text);
    }

    public function test_media_player_filler_is_not_stored_as_a_transcript_line(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'Tune in the next one.']),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk()
            ->assertJsonPath('silent', true);

        $this->assertSame(0, $call->transcriptLines()->count());
    }

    public function test_a_real_short_sentence_is_still_stored(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'Send it over.']),
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload()),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk()
            ->assertJsonPath('transcript', 'Send it over.');

        $this->assertSame('Send it over.', $call->transcriptLines()->sole()->text);
    }

    public function test_a_provider_failure_during_analysis_is_reported_rather_than_returning_an_empty_shelf(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'What is in the enterprise tier?']),
            'api.openai.com/v1/chat/completions*' => Http::response([
                'error' => [
                    'message' => 'Failed to generate JSON.',
                    'code' => 'json_validate_failed',
                    'failed_generation' => 'max completion tokens reached before generating a valid document',
                ],
            ], 400),
        ]);

        $call = Call::factory()->create();

        /* The upload succeeded, so it must not fail — the audio is stored and the
           next window can still produce cards. But an empty `findings` array is
           indistinguishable from "the model had nothing to say", and a run of
           those is what made the shelf look permanently broken. */
        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk()
            ->assertJsonCount(0, 'findings')
            ->assertJsonPath('analysis_failed', true);

        $this->assertDatabaseHas('transcript_lines', [
            'call_id' => $call->id,
            'speaker' => 'customer',
        ], 'deally');
    }

    public function test_a_healthy_but_empty_analysis_is_not_reported_as_a_failure(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'We will need it before the end of the quarter.']),
            'api.openai.com/v1/chat/completions*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['signals' => [], 'recommendations' => []])]]],
            ]),
        ]);

        $call = Call::factory()->create();

        // Nothing to report is a real outcome, not a fault, and must not be shown
        // as one or the rep loses trust in the warning.
        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk()
            ->assertJsonPath('analysis_failed', false);
    }

    public function test_analysis_leaves_room_for_the_reasoning_a_model_does_first(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'What is in the enterprise tier?']),
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload()),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk();

        $body = Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'chat/completions'))
            ->first()[0]->data();

        // Reasoning tokens are drawn from this same ceiling, so a budget sized for
        // the answer alone truncates the model before it emits any JSON.
        $this->assertGreaterThanOrEqual(2000, $body['max_completion_tokens'] ?? $body['max_tokens']);
    }

    public function test_provider_failure_returns_a_controlled_503(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['error' => 'rate limited'], 429),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertStatus(503)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'transcription_unavailable')
            ->assertJsonPath('retryable', true);

        $this->assertSame(0, $call->transcriptLines()->count());
    }

    public function test_missing_credentials_do_not_fabricate_a_transcript(): void
    {
        config(['services.live_ai.driver' => 'openai', 'services.openai.key' => null]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertStatus(503)
            ->assertJsonPath('error', 'transcription_unavailable');

        $this->assertSame(0, $call->transcriptLines()->count());
    }

    public function test_completed_calls_reject_new_chunks(): void
    {
        Http::fake();

        $call = Call::factory()->create(['status' => Call::STATUS_COMPLETED]);

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertStatus(409)
            ->assertJsonPath('error', 'call_completed');

        Http::assertNothingSent();
    }

    /* ---------- replayable audio ---------- */

    public function test_a_window_is_kept_even_when_the_provider_refuses_to_transcribe_it(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['error' => 'rate limited'], 429),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload(['chunk_id' => 'kept-window']))
            ->assertStatus(503);

        /* The browser tells a rep that "the call is still being recorded" when a
           window fails, so the audio has to outlive the request that failed. A
           provider outage is exactly when a rep most wants it back. */
        $recording = CallRecording::query()->sole();

        $this->assertSame('customer', $recording->source);
        $this->assertSame('kept-window', $recording->client_chunk_id);
        $this->assertSame(8000, $recording->duration_ms);
        Storage::disk('local')->assertExists($recording->path);
    }

    public function test_a_silent_window_is_kept_too(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => '   ']),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload(['chunk_id' => 'quiet']))
            ->assertOk()
            ->assertJsonPath('silent', true);

        $this->assertSame(0, $call->transcriptLines()->count());
        $this->assertSame(1, $call->recordings()->count());
    }

    public function test_a_retried_window_does_not_duplicate_its_recording(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'We need SSO before we can sign.']),
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload()),
        ]);

        $call = Call::factory()->create();
        $payload = $this->chunkPayload(['chunk_id' => 'same-window']);

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $payload)
            ->assertOk();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertSame(1, $call->recordings()->count());
    }

    public function test_a_window_from_another_call_is_not_reachable_through_this_calls_url(): void
    {
        Http::fake();

        $mine = Call::factory()->create();
        $theirs = Call::factory()->create();

        $recording = CallRecording::factory()->create([
            'call_id' => $theirs->id,
            'path' => 'calls/'.$theirs->id.'/customer-0.webm',
        ]);

        Storage::disk('local')->put($recording->path, 'bytes');

        $this->actingAs($this->alice())
            ->get(route('deally.calls.recording', ['call' => $mine, 'recording' => $recording->id]))
            ->assertNotFound();
    }

    public function test_a_stored_window_is_streamed_back_to_the_review(): void
    {
        Http::fake();

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk();

        $recording = $call->recordings()->sole();

        /* The windows are recorded from a stream with no video track, but a
           browser may still label the container `video/webm`, and an <audio>
           element refuses that outright — replay would fail on a codec the
           file actually contains. */
        $this->actingAs($this->alice())
            ->get(route('deally.calls.recording', ['call' => $call, 'recording' => $recording->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/webm');
    }

    /* ---------- the review holds the whole exchange ---------- */

    public function test_the_review_shows_what_the_assistant_said_next_to_the_line_that_triggered_it(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'Your pricing looks higher than we can approve.']),
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload()),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk();

        /* A review that shows only the questions is not a review: the rep cannot
           see what they were told to say without re-running the call. */
        $this->actingAs($this->alice())
            ->get(route('deally.calls.review', $call))
            ->assertOk()
            ->assertSee('Your pricing looks higher than we can approve.')
            ->assertSee('DeAlly said')
            ->assertSee('Objection · Reply')
            ->assertSee('Offer the two-week pilot at a reduced rate.');
    }

    public function test_the_review_replays_a_captured_call(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'Send me the security pack.']),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk();

        $this->actingAs($this->alice())
            ->get(route('deally.calls.review', $call))
            ->assertOk()
            ->assertSee('replay-index', false)
            ->assertSee('Play from this moment');
    }

    public function test_a_call_with_no_audio_says_so_instead_of_offering_a_dead_player(): void
    {
        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->get(route('deally.calls.review', $call))
            ->assertOk()
            ->assertSee('No audio was kept for this call')
            ->assertDontSee('replay-index', false);
    }

    public function test_windows_are_ordered_by_when_they_were_captured_not_by_each_streams_own_counter(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'Tell me about the enterprise tier.']),
        ]);

        $call = Call::factory()->create();

        /* Each source counts from zero independently, so the customer and agent
           streams share a sequence number. Ordering by it would interleave the
           two streams arbitrarily and produce a scrambled replay. */
        $customerLate = $this->chunkPayload([
            'speaker' => 'customer',
            'client_sequence' => 0,
            'chunk_id' => 'customer-0',
            'started_at_ms' => 2_000,
        ]);

        $agentEarly = $this->chunkPayload([
            'speaker' => 'agent',
            'client_sequence' => 0,
            'chunk_id' => 'agent-0',
            'started_at_ms' => 1_000,
        ]);

        $customerNext = $this->chunkPayload([
            'speaker' => 'customer',
            'client_sequence' => 1,
            'chunk_id' => 'customer-1',
            'started_at_ms' => 3_000,
        ]);

        foreach ([$customerLate, $agentEarly, $customerNext] as $payload) {
            $this->actingAs($this->alice())
                ->postJson(route('deally.calls.live.transcribe', $call), $payload)
                ->assertOk();
        }

        $order = $call->recordings()->pluck('client_chunk_id')->all();

        $this->assertSame(['agent-0', 'customer-0', 'customer-1'], $order);
    }

    public function test_a_question_asked_mid_call_is_kept_for_the_review(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions*' => Http::response([
                'choices' => [['message' => ['content' => 'The Enterprise tier is $15 per user per month.']]],
            ]),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.query', $call), ['text' => 'What does Enterprise cost?'])
            ->assertOk();

        $this->assertDatabaseHas('call_queries', [
            'call_id' => $call->id,
            'prompt' => 'What does Enterprise cost?',
            'provider' => 'openai',
        ], 'deally');

        /* The live panel shows this as a chat. A chat the rep cannot look back
           at is why the review held only the questions. */
        $this->actingAs($this->alice())
            ->get(route('deally.calls.review', $call))
            ->assertOk()
            ->assertSee('You asked DeAlly')
            ->assertSee('What does Enterprise cost?')
            ->assertSee('The Enterprise tier is $15 per user per month.');
    }

    public function test_the_downloaded_transcript_carries_the_assistants_half_too(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'We need SSO before we sign.']),
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload()),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk();

        $download = $this->actingAs($this->alice())
            ->get(route('deally.calls.transcript.download', $call))
            ->assertOk();

        $content = $download->streamedContent();

        $this->assertStringContainsString('We need SSO before we sign.', $content);
        $this->assertStringContainsString('DEALLY (OBJECTION): Customer pushed back on price.', $content);
    }

    /* ---------- lifecycle ---------- */

    public function test_start_endpoint_opens_the_session_once(): void
    {
        Http::fake();

        $call = Call::factory()->create(['status' => Call::STATUS_SCHEDULED]);

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.start', $call))
            ->assertOk()
            ->assertJsonPath('status', Call::STATUS_IN_PROGRESS)
            ->assertJsonPath('provider', 'openai');

        $startedAt = $call->fresh()->started_at;

        $this->actingAs($this->alice())->postJson(route('deally.calls.live.start', $call))->assertOk();

        $this->assertEquals($startedAt, $call->fresh()->started_at);
    }

    public function test_completed_calls_cannot_be_restarted(): void
    {
        Http::fake();

        $call = Call::factory()->create(['status' => Call::STATUS_COMPLETED]);

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.start', $call))
            ->assertStatus(409);
    }

    public function test_ending_a_recorded_call_keeps_its_transcript(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'Send us the compliance sheet.']),
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload()),
        ]);

        $call = Call::factory()->create();
        $user = $this->alice();

        $this->actingAs($user)->postJson(route('deally.calls.live.start', $call))->assertOk();
        $this->actingAs($user)->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk();

        $this->actingAs($user)
            ->post(route('deally.calls.end', $call), ['sentiment' => 'positive'])
            ->assertRedirect(route('deally.calls.summary', $call));

        $call->refresh();

        $this->assertSame(Call::STATUS_COMPLETED, $call->status);
        $this->assertNotNull($call->ended_at);
        $this->assertSame('Send us the compliance sheet.', $call->transcriptLines()->sole()->text);
    }

    /* ---------- analysis behaviour ---------- */

    public function test_analysis_is_throttled_between_chunks(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'Another customer line.']),
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload()),
        ]);

        config(['services.live_ai.analysis_min_interval' => 60]);

        $call = Call::factory()->create();
        $user = $this->alice();

        $this->actingAs($user)->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertJsonCount(2, 'findings');

        $this->actingAs($user)
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload(['client_sequence' => 1]))
            ->assertJsonCount(0, 'findings');

        // Two transcription calls plus exactly one analysis call.
        Http::assertSentCount(3);
    }

    public function test_provider_output_is_validated_before_it_is_stored(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'We are evaluating two other vendors.']),
            'api.openai.com/v1/chat/completions*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'signals' => [
                        // Unknown kind, empty text and an out-of-window line id
                        // must all be dropped rather than persisted.
                        ['kind' => 'not_a_real_kind', 'text' => 'Invented', 'quote' => '', 'confidence' => 5, 'source_line' => 9999],
                        ['kind' => 'intent', 'text' => '   ', 'quote' => '', 'confidence' => 0.4, 'source_line' => 9999],
                        ['kind' => 'competitor', 'text' => 'Evaluating rivals.', 'quote' => 'two other vendors', 'confidence' => 1.7, 'source_line' => 9999],
                    ],
                    'recommendations' => [
                        ['role' => 'hallucinate', 'label' => 'x', 'body' => 'y', 'package' => '', 'source' => '', 'confidence' => 0.5, 'source_line' => 1],
                    ],
                ])]]],
            ]),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk()
            ->assertJsonCount(1, 'findings');

        $finding = CallFinding::query()->sole();
        $line = $call->transcriptLines()->sole();

        $this->assertSame('competitor', $finding->kind);
        $this->assertSame(1.0, $finding->confidence);
        // The invented line id is discarded in favour of a line we stored.
        $this->assertSame($line->id, $finding->transcript_line_id);
    }

    public function test_unreadable_analysis_payload_degrades_without_failing_the_chunk(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'Can you do better on price?']),
            'api.openai.com/v1/chat/completions*' => Http::response([
                'choices' => [['message' => ['content' => 'not json at all']]],
            ]),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk()
            ->assertJsonPath('transcript', 'Can you do better on price?')
            ->assertJsonCount(0, 'findings');

        $this->assertSame(1, $call->transcriptLines()->count());
    }

    /* ---------- findings, gaps, feedback ---------- */

    public function test_knowledge_gap_signal_creates_a_linked_gap(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'Do you support HIPAA?']),
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload([
                'signals' => [[
                    'kind' => 'knowledge_gap',
                    'text' => 'No documented HIPAA answer.',
                    'quote' => 'Do you support HIPAA?',
                    'confidence' => 0.7,
                    'source_line' => null,
                ]],
            ])),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk();

        $this->assertDatabaseHas('knowledge_gaps', [
            'type' => 'gap',
            'call_id' => $call->id,
            'call_finding_id' => CallFinding::query()->where('kind', 'knowledge_gap')->value('id'),
        ], 'deally');
    }

    public function test_unhelpful_feedback_creates_one_gap_and_survives_repeats(): void
    {
        Http::fake();

        $call = Call::factory()->create();
        $finding = CallFinding::factory()->create(['call_id' => $call->id]);
        $url = route('deally.calls.live.finding.feedback', ['call' => $call, 'finding' => $finding]);

        $this->actingAs($this->alice())
            ->postJson($url, ['status' => 'unhelpful'])
            ->assertOk()
            ->assertJsonPath('status', 'unhelpful');

        $this->actingAs($this->alice())
            ->postJson($url, ['status' => 'unhelpful'])
            ->assertOk();

        $this->assertSame(1, $call->gaps()->count());
        $this->assertDatabaseHas('knowledge_gaps', [
            'call_finding_id' => $finding->id,
            'text' => $finding->body,
        ], 'deally');
    }

    public function test_feedback_cannot_target_a_finding_from_another_call(): void
    {
        Http::fake();

        $call = Call::factory()->create();
        $finding = CallFinding::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.finding.feedback', ['call' => $call, 'finding' => $finding]), [
                'status' => 'unhelpful',
            ])
            ->assertStatus(404);
    }

    public function test_analysis_is_told_what_was_already_reported(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'And how long is the trial?']),
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload()),
        ]);

        $call = Call::factory()->create();
        CallFinding::factory()->create([
            'call_id' => $call->id,
            'body' => 'No knowledge base entry covers the driving distance question.',
        ]);

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk();

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/chat/completions')) {
                return false;
            }

            $body = (string) $request->body();

            // The model must see what is already on the shelf, and be told the
            // newest customer line is the one that matters.
            return str_contains($body, 'No knowledge base entry covers the driving distance question.')
                && str_contains($body, 'do not repeat');
        });
    }

    public function test_only_a_real_knowledge_entry_is_rendered_as_a_document(): void
    {
        KnowledgeEntry::factory()->create(['title' => 'Enterprise Tier Pricing']);

        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'What is in the enterprise tier?']),
            'api.openai.com/v1/chat/completions*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'signals' => [],
                    'recommendations' => [
                        ['role' => 'reference', 'label' => 'pricing', 'body' => 'Let me walk you through the enterprise tier.', 'package' => 'default', 'source' => 'agent', 'confidence' => 0.8, 'source_line' => 1],
                        ['role' => 'say', 'label' => 'pricing', 'body' => 'Here is the enterprise tier pricing.', 'package' => 'Enterprise Tier Pricing', 'source' => 'agent', 'confidence' => 0.9, 'source_line' => 1],
                    ],
                ])]]],
            ]),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk()
            ->assertJsonCount(2, 'findings');

        $packages = CallFinding::query()->orderBy('id')->pluck('package', 'body');

        // "default" is not a document the rep can open, so the footer states the
        // follow-up commitment instead of pretending otherwise.
        $this->assertSame(
            LiveAssistant::NO_KNOWLEDGE_PACKAGE,
            $packages['Let me walk you through the enterprise tier.']
        );
        $this->assertSame('Enterprise Tier Pricing', $packages['Here is the enterprise tier pricing.']);
    }

    public function test_an_answer_from_own_knowledge_is_labelled_as_model_knowledge(): void
    {
        KnowledgeEntry::factory()->create(['title' => 'Enterprise Tier Pricing']);

        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'Is USA a third world country?']),
            'api.openai.com/v1/chat/completions*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'signals' => [],
                    'recommendations' => [
                        [
                            'role' => 'say',
                            'label' => 'definition',
                            'body' => 'No, the US is a high-income developed economy, not a third-world country.',
                            // The model may echo a real entry title even when the
                            // answer came from its own head, so the declared
                            // grounding is what decides the footer.
                            'package' => 'Enterprise Tier Pricing',
                            'source' => 'model knowledge',
                            'grounding' => 'model',
                            'confidence' => 0.95,
                            'source_line' => 1,
                        ],
                    ],
                ])]]],
            ]),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk()
            ->assertJsonCount(1, 'findings');

        // A model-grounded answer is welcome, but the rep must be told there is
        // no document behind it rather than being sent to open one.
        $this->assertSame(
            LiveAssistant::MODEL_KNOWLEDGE_PACKAGE,
            CallFinding::query()->sole()->package
        );
    }

    public function test_an_unrecognised_grounding_is_treated_as_the_knowledge_base(): void
    {
        KnowledgeEntry::factory()->create(['title' => 'Enterprise Tier Pricing']);

        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'What is in the enterprise tier?']),
            'api.openai.com/v1/chat/completions*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'signals' => [],
                    'recommendations' => [
                        ['role' => 'say', 'label' => 'pricing', 'body' => 'Here is the enterprise tier pricing.', 'package' => 'Enterprise Tier Pricing', 'source' => 'agent', 'grounding' => 'somewhere', 'confidence' => 0.9, 'source_line' => 1],
                    ],
                ])]]],
            ]),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk()
            ->assertJsonCount(1, 'findings');

        // Provider output is untrusted: an unrecognised provenance is read as
        // "open a document", which is the safe side of the mistake.
        $this->assertSame('Enterprise Tier Pricing', CallFinding::query()->sole()->package);
    }

    public function test_the_assistant_is_told_it_may_answer_general_questions_itself(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'Is USA a third world country?']),
            'api.openai.com/v1/chat/completions*' => Http::response($this->analysisPayload(['signals' => [], 'recommendations' => []])),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk();

        $prompt = Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'chat/completions'))
            ->first()[0]->data()['messages'][0]['content'];

        // Refusing a general fact the customer can already verify costs the agent
        // credibility, so the model is told to answer — while still being barred
        // from inventing our own commercial terms.
        $this->assertStringContainsString('from your own knowledge', $prompt);
        $this->assertStringNotContainsString('Never answer from your own', $prompt);

        // Everyday how-tos about the customer's own devices (e.g. "how do I turn
        // on data roaming on a Samsung S24 Ultra?") are model knowledge too — only
        // the company's own commercial terms may not be invented.
        $this->assertStringContainsString('everyday how-tos and support', $prompt);
        $this->assertStringContainsString("customer's own devices", $prompt);
        $this->assertStringContainsString('well-documented answer is not a gap', $prompt);
    }

    public function test_findings_are_restored_on_the_live_page(): void
    {
        Http::fake();

        $call = Call::factory()->create();
        CallFinding::factory()->create(['call_id' => $call->id, 'body' => 'Restored suggestion']);

        $this->actingAs($this->alice())
            ->get(route('deally.calls.live', $call))
            ->assertOk()
            ->assertSee('Restored suggestion', false)
            ->assertSee('id="initial-findings"', false);
    }

    public function test_live_shelf_only_renders_the_newest_findings(): void
    {
        config(['services.live_ai.shelf_limit' => 2]);

        $call = Call::factory()->create();
        CallFinding::factory()->create(['call_id' => $call->id, 'body' => 'Oldest finding']);
        CallFinding::factory()->create(['call_id' => $call->id, 'body' => 'Middle finding']);
        CallFinding::factory()->create(['call_id' => $call->id, 'body' => 'Newest finding']);

        $html = $this->actingAs($this->alice())
            ->get(route('deally.calls.live', $call))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Newest finding', $html);
        $this->assertStringContainsString('Middle finding', $html);
        $this->assertStringNotContainsString('Oldest finding', $html);
        $this->assertStringContainsString('Newest 2 shown', $html);

        // Capping the shelf must never delete anything.
        $this->assertSame(3, $call->findings()->count());
    }

    /* ---------- provider configuration ---------- */

    public function test_groq_driver_uses_the_configured_endpoint_and_strict_schema(): void
    {
        config([
            'services.live_ai.driver' => 'groq',
            'services.groq.key' => 'groq-test-key',
            'services.groq.chat_model' => 'openai/gpt-oss-20b',
        ]);

        Http::fake([
            'api.groq.com/openai/v1/audio/transcriptions*' => Http::response(['text' => 'Does your platform support HIPAA?']),
            'api.groq.com/openai/v1/chat/completions*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['signals' => [], 'recommendations' => []])]]],
            ]),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), $this->chunkPayload())
            ->assertOk();

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://api.groq.com/openai/v1/audio/transcriptions') {
                return false;
            }

            // The transcription body is multipart, so the model travels as a
            // form part rather than a JSON key.
            return $request->hasHeader('Authorization', 'Bearer groq-test-key')
                && str_contains((string) $request->body(), 'name="model"')
                && str_contains((string) $request->body(), 'whisper-large-v3-turbo');
        });

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://api.groq.com/openai/v1/chat/completions') {
                return false;
            }

            $payload = $request->data();

            return ($payload['response_format']['type'] ?? null) === 'json_schema'
                && ($payload['response_format']['json_schema']['strict'] ?? null) === true
                && ($payload['model'] ?? null) === 'openai/gpt-oss-20b'
                && array_key_exists('max_completion_tokens', $payload)
                && ! array_key_exists('max_tokens', $payload);
        });
    }

    public function test_provider_key_is_never_sent_to_the_browser(): void
    {
        Http::fake();

        $call = Call::factory()->create();

        $html = $this->actingAs($this->alice())
            ->get(route('deally.calls.live', $call))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('test-key', (string) $html);
        $this->assertStringNotContainsString('api.groq.com', (string) $html);
    }

    /* ---------- existing assistant behaviour ---------- */

    public function test_query_endpoint_returns_answer(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions*' => Http::response([
                'choices' => [['message' => ['content' => 'Our enterprise tier is $15/user/mo with volume discounts.']]],
            ]),
        ]);

        $call = Call::factory()->create(['company' => 'Acme Corp']);

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.query', $call), ['text' => 'What does enterprise pricing look like?'])
            ->assertOk()
            ->assertJsonPath('answer', 'Our enterprise tier is $15/user/mo with volume discounts.');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && str_contains($request['messages'][0]['content'], 'Acme Corp');
        });
    }

    public function test_a_long_paragraph_question_is_accepted_and_answered(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions*' => Http::response([
                'choices' => [['message' => ['content' => 'Break the rollout into regional pilots with staggered go-live dates.']]],
            ]),
        ]);

        $call = Call::factory()->create(['company' => 'Acme Corp']);

        // ~2,600 characters — comfortably beyond the old 1,000-char cap.
        $longQuestion = implode(' ', array_fill(0, 25, 'How does the rollout work across multiple regions with staggered pilot timelines and approval gates?'));

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.query', $call), ['text' => $longQuestion])
            ->assertOk()
            ->assertJsonPath('answer', 'Break the rollout into regional pilots with staggered go-live dates.');
    }

    public function test_global_ask_returns_a_knowledge_grounded_answer(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions*' => Http::response([
                'choices' => [['message' => ['content' => 'Every plan runs on per-tenant isolation.']]],
            ]),
        ]);

        $this->actingAs($this->alice())
            ->postJson(route('deally.ask'), ['text' => 'How do you keep our data isolated?'])
            ->assertOk()
            ->assertJsonPath('answer', 'Every plan runs on per-tenant isolation.');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && str_contains($request['messages'][0]['content'], 'Knowledge base');
        });
    }

    public function test_provider_outage_degrades_a_query_instead_of_erroring(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions*' => Http::response(['error' => 'upstream'], 500),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.query', $call), ['text' => 'What does enterprise pricing look like?'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'query_failed');
    }

    public function test_guest_is_redirected_from_transcribe_endpoint(): void
    {
        Http::fake();

        $call = Call::factory()->create();

        $this->postJson(route('deally.calls.live.transcribe', $call), [
            'audio' => UploadedFile::fake()->createWithContent('audio.webm', 'fake-audio-bytes'),
        ])->assertStatus(401);

        Http::assertNothingSent();
    }
}
