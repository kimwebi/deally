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
