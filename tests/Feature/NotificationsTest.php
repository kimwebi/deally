<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Core\Models\User;
use Deally\Core\Services\ActivityLogger;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Core\Services\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class NotificationsTest extends TestCase
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

    public function test_notification_is_shown_with_unread_state(): void
    {
        $alice = $this->user('alice@example.com');

        app(Notifier::class)->notify($alice, 'Call complete', 'Review Acme Corp within 24 hours.', 'call');

        $this->actingAs($alice)
            ->get(route('deally.notifications.index'))
            ->assertOk()
            ->assertSee('Call complete')
            ->assertSee('Review Acme Corp within 24 hours')
            ->assertSee('new');
    }

    public function test_mark_read_and_read_all(): void
    {
        $alice = $this->user('alice@example.com');

        $first = app(Notifier::class)->notify($alice, 'Call complete', 'Review one.', 'call');
        app(Notifier::class)->notify($alice, 'Task reminder', 'Follow up on Globex.', 'task');

        $this->actingAs($alice)
            ->post(route('deally.notifications.read', $first))
            ->assertRedirect();

        $this->assertTrue($first->refresh()->read());

        $this->actingAs($alice)
            ->post(route('deally.notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $alice->unreadNotifications()->count());
    }

    public function test_notification_cannot_be_read_by_another_user(): void
    {
        $alice = $this->user('alice@example.com');
        $charlie = $this->user('charlie@example.com');

        $notification = app(Notifier::class)->notify($alice, 'Private', 'Only for Alice.', 'call');

        $this->actingAs($charlie)
            ->post(route('deally.notifications.read', $notification))
            ->assertNotFound();
    }

    public function test_activity_feed_is_tenant_scoped_and_filterable(): void
    {
        $alice = $this->user('alice@example.com');

        $this->actingAs($alice);

        app(ActivityLogger::class)->log('call.ended', 'Ended call with Acme Corp.');
        app(ActivityLogger::class)->log('call.query_failed', 'Live query failure.', [], 'error');

        $this->get(route('deally.activity.index'))
            ->assertOk()
            ->assertSee('Ended call with Acme Corp.')
            ->assertSee('Live query failure.');

        $this->get(route('deally.activity.index', ['level' => 'error']))
            ->assertOk()
            ->assertSee('Live query failure.')
            ->assertDontSee('Ended call with Acme Corp.');
    }

    public function test_sales_agent_cannot_view_activity_feed(): void
    {
        $this->actingAs($this->user('charlie@example.com'))
            ->get(route('deally.activity.index'))
            ->assertForbidden();
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
