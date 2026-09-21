<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Deally\Calls\Models\Call;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Core\Services\TenantConnectionBinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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

        config(['services.openai.key' => 'test-key']);
    }

    protected function tearDown(): void
    {
        foreach (glob(database_path('tenants').DIRECTORY_SEPARATOR.'*.sqlite') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_transcribe_endpoint_stores_lines_and_returns_suggestions(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => 'Can you support HIPAA compliance?']),
            'api.openai.com/v1/chat/completions*' => Http::response([
                'choices' => [['message' => ['content' => "1. Yes, we support HIPAA.\n2. Let me send you our compliance sheet."]]],
            ]),
        ]);

        $call = Call::factory()->create(['company' => 'Acme Corp']);

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), [
                'audio' => UploadedFile::fake()->createWithContent('audio.webm', 'fake-audio-bytes'),
            ])
            ->assertOk()
            ->assertJsonPath('transcript', 'Can you support HIPAA compliance?')
            ->assertJsonCount(2, 'suggestions');

        $this->assertDatabaseHas('transcript_lines', [
            'call_id' => $call->id,
            'speaker' => 'customer',
            'text' => 'Can you support HIPAA compliance?',
        ], 'deally');
    }

    public function test_transcribe_endpoint_handles_failed_transcription(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(['text' => '   ']),
        ]);

        $call = Call::factory()->create();

        $this->actingAs($this->alice())
            ->postJson(route('deally.calls.live.transcribe', $call), [
                'audio' => UploadedFile::fake()->createWithContent('audio.webm', 'fake-audio-bytes'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'transcription_failed');

        $this->assertSame(0, $call->transcriptLines()->count());
    }

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

    public function test_guest_is_redirected_from_transcribe_endpoint(): void
    {
        Http::fake();

        $call = Call::factory()->create();

        $this->postJson(route('deally.calls.live.transcribe', $call), [
            'audio' => UploadedFile::fake()->createWithContent('audio.webm', 'fake-audio-bytes'),
        ])->assertStatus(401);

        Http::assertNothingSent();
    }

    private function alice(): User
    {
        return User::query()->where('email', 'alice@example.com')->firstOrFail();
    }
}
