<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Calls\Models\Call;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Core\Services\TenantConnectionBinder;
use Deally\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class CallLifecycleTest extends TestCase
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

    public function test_ending_a_call_without_sentiment_defaults_to_neutral(): void
    {
        [$user, $call] = $this->startCall();

        $this->actingAs($user)
            ->post(route('deally.calls.end', $call), [
                'duration' => '357:03',
                'sentiment' => '',
                'notes' => 'Go home after this one.',
            ])
            ->assertRedirect(route('deally.calls.summary', $call));

        $call->refresh();

        $this->assertSame('neutral', $call->sentiment);
        $this->assertSame('357:03', $call->duration);
        $this->assertSame('Go home after this one.', $call->notes);
        $this->assertNotNull($call->summary);

        $this->assertTrue(Task::query()->where('title', "Review Call — {$call->company}")->exists());
    }

    public function test_ending_a_call_in_demo_mode_persists_the_full_conversation(): void
    {
        config()->set('services.openai.key', '');

        [$user, $call] = $this->startCall();

        $this->actingAs($user)
            ->post(route('deally.calls.end', $call), [
                'duration' => '357:03',
                'sentiment' => 'neutral',
                'notes' => '',
            ])
            ->assertRedirect(route('deally.calls.summary', $call));

        $lines = $call->transcriptLines()->orderBy('sequence')->get();

        $this->assertCount(10, $lines);
        $this->assertSame(0, $lines->first()->is_agent);
        $this->assertSame(1, $lines->offsetGet(1)->is_agent);
        $this->assertStringContainsString('legacy tool', $lines->first()->text);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $lines->pluck('sequence')->all());
        $this->assertSame(5, $lines->where('is_agent', 1)->count());
    }

    public function test_ending_a_call_with_captured_lines_completes_the_conversation(): void
    {
        config()->set('services.openai.key', '');

        [$user, $call] = $this->startCall();

        $call->transcriptLines()->create([
            'speaker' => 'customer',
            'is_agent' => false,
            'text' => 'Real captured line.',
            'sequence' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('deally.calls.end', $call), [
                'duration' => '357:03',
                'sentiment' => 'neutral',
                'notes' => '',
            ])
            ->assertRedirect(route('deally.calls.summary', $call));

        $lines = $call->transcriptLines()->orderBy('sequence')->get();

        $this->assertCount(10, $lines);
        $this->assertSame(5, $lines->where('is_agent', 1)->count());
    }

    public function test_ending_a_call_with_a_provider_configured_does_not_seed_demo_lines(): void
    {
        config()->set('services.openai.key', 'sk-test');

        [$user, $call] = $this->startCall();

        $this->actingAs($user)
            ->post(route('deally.calls.end', $call), [
                'duration' => '357:03',
                'sentiment' => 'neutral',
                'notes' => '',
            ])
            ->assertRedirect(route('deally.calls.summary', $call));

        $this->assertSame(0, $call->transcriptLines()->count());
    }

    public function test_ending_a_call_marks_it_completed(): void
    {
        [$user, $call] = $this->startCall();

        $this->actingAs($user)
            ->post(route('deally.calls.end', $call), [
                'duration' => '357:03',
                'sentiment' => 'neutral',
                'notes' => '',
            ])
            ->assertRedirect(route('deally.calls.summary', $call));

        $this->assertSame(Call::STATUS_COMPLETED, $call->refresh()->status);
    }

    public function test_demo_transcript_is_rendered_on_the_review_page(): void
    {
        config()->set('services.openai.key', '');

        [$user, $call] = $this->startCall();

        $this->actingAs($user)
            ->post(route('deally.calls.end', $call), [
                'duration' => '357:03',
                'sentiment' => 'neutral',
                'notes' => '',
            ])
            ->assertRedirect(route('deally.calls.summary', $call));

        $this->actingAs($user)
            ->get(route('deally.calls.review', $call))
            ->assertOk()
            ->assertDontSee('No transcript lines saved yet')
            ->assertSee('legacy tool');
    }

    /**
     * @return array{0: User, 1: Call}
     */
    private function startCall(): array
    {
        $user = User::query()->where('email', 'alice@example.com')->firstOrFail();
        $membership = $user->memberships()->active()->with('tenant')->first();

        app(TenantConnectionBinder::class)->bind($membership->tenant);

        $this->actingAs($user)
            ->post(route('deally.calls.store'), [
                'name' => 'Enterprise Suite demo',
                'company' => 'Acme Corp',
            ])
            ->assertRedirect();

        $call = Call::query()->where('company', 'Acme Corp')->latest('id')->firstOrFail();

        return [$user, $call];
    }
}
