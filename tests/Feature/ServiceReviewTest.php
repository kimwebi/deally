<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Pipeline\Models\Customer;
use Deally\Pipeline\Models\ServiceReviewSchedule;
use Deally\Pipeline\Models\ServiceReviewSession;
use Deally\Pipeline\Services\RiskService;
use Deally\Pipeline\Services\ServiceReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class ServiceReviewTest extends TestCase
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

    public function test_service_reviews_can_be_set_up_and_preseed_an_upcoming_series(): void
    {
        $charlie = $this->user('charlie@example.com');
        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();

        $this->actingAs($charlie)
            ->post(route('deally.service-reviews.setup', $stark))
            ->assertSessionHas('toast');

        $schedule = $stark->activeServiceReview();
        $this->assertNotNull($schedule);
        $this->assertSame(ServiceReviewSchedule::STATUS_ACTIVE, $schedule->status);

        $this->assertSame(3, app(ServiceReviewService::class)->upcomingSessions($schedule)->count());
    }

    public function test_setup_is_rejected_while_an_active_schedule_exists(): void
    {
        $charlie = $this->user('charlie@example.com');
        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();

        app(ServiceReviewService::class)->setup($stark);

        $this->actingAs($charlie)
            ->post(route('deally.service-reviews.setup', $stark))
            ->assertSessionHas('toast');

        $this->assertSame(1, $stark->serviceReviews()->count());
    }

    public function test_cadence_can_be_changed_without_disturbing_existing_reviews(): void
    {
        $charlie = $this->user('charlie@example.com');
        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();
        $schedule = app(ServiceReviewService::class)->setup($stark);

        $this->actingAs($charlie)
            ->patch(route('deally.service-reviews.cadence', $schedule), [
                'cadence_days' => 7,
                'mode' => 'future',
            ])
            ->assertSessionHas('toast');

        $this->assertSame(7, $schedule->fresh()->cadence_days);
        $this->assertSame(3, app(ServiceReviewService::class)->upcomingSessions($schedule->fresh())->count());
    }

    public function test_regenerating_the_cadence_rebuilds_the_upcoming_series(): void
    {
        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();
        $schedule = app(ServiceReviewService::class)->setup($stark);

        // Age the pre-seeded series so regenerating visibly replaces it.
        $schedule->sessions()->get()->each(fn (ServiceReviewSession $session, int $i) => $session->update([
            'scheduled_at' => now()->addYears(3)->addDays($i * 30),
        ]));

        app(ServiceReviewService::class)->changeCadence($schedule, 7, 'regenerate');

        $schedule->refresh();

        $this->assertSame(7, $schedule->cadence_days);
        $this->assertSame(3, app(ServiceReviewService::class)->upcomingSessions($schedule)->count());

        foreach (app(ServiceReviewService::class)->upcomingSessions($schedule) as $session) {
            $this->assertTrue($session->scheduled_at->isBefore(now()->addDays(30)));
        }
    }

    public function test_a_session_can_be_rescheduled_and_time_clashes_are_rejected(): void
    {
        $charlie = $this->user('charlie@example.com');
        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();
        $schedule = app(ServiceReviewService::class)->setup($stark);

        $session = $schedule->sessions()->first();
        $newTime = now()->addDays(2)->setTime(14, 0, 0);

        $this->actingAs($charlie)
            ->patch(route('deally.service-reviews.sessions.reschedule', $session), [
                'scheduled_at' => $newTime->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHas('toast');

        $this->assertSame(
            $newTime->format('Y-m-d H:i:s'),
            $session->fresh()->scheduled_at->format('Y-m-d H:i:s')
        );

        // Another session already occupies the target slot.
        $occupied = $schedule->sessions()->whereKeyNot($session->getKey())->first();

        $this->actingAs($charlie)
            ->patch(route('deally.service-reviews.sessions.reschedule', $session->fresh()), [
                'scheduled_at' => $occupied->scheduled_at->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasErrors('scheduled_at');
    }

    public function test_a_session_cannot_be_rescheduled_into_the_past(): void
    {
        $charlie = $this->user('charlie@example.com');
        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();
        $schedule = app(ServiceReviewService::class)->setup($stark);

        $session = $schedule->sessions()->first();

        $this->actingAs($charlie)
            ->patch(route('deally.service-reviews.sessions.reschedule', $session), [
                'scheduled_at' => now()->subDay()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasErrors('scheduled_at');
    }

    public function test_holding_a_session_marks_it_held_and_topped_up_the_series(): void
    {
        $charlie = $this->user('charlie@example.com');
        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();
        $schedule = app(ServiceReviewService::class)->setup($stark);

        $session = $schedule->sessions()->first();

        $this->actingAs($charlie)
            ->post(route('deally.service-reviews.sessions.hold', $session), ['notes' => 'Held — waiting on exec calendar.'])
            ->assertSessionHas('toast');

        $this->assertSame(ServiceReviewSession::STATUS_HELD, $session->fresh()->status);
        $this->assertSame('Held — waiting on exec calendar.', $session->fresh()->notes);
        $this->assertSame(3, app(ServiceReviewService::class)->upcomingSessions($schedule)->count());
    }

    public function test_cancelling_a_session_removes_it_and_keeps_three_upcoming(): void
    {
        $charlie = $this->user('charlie@example.com');
        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();
        $schedule = app(ServiceReviewService::class)->setup($stark);

        $session = $schedule->sessions()->first();

        $this->actingAs($charlie)
            ->post(route('deally.service-reviews.sessions.cancel', $session))
            ->assertSessionHas('toast');

        $this->assertSame(ServiceReviewSession::STATUS_CANCELLED, $session->fresh()->status);
        $this->assertSame(3, app(ServiceReviewService::class)->upcomingSessions($schedule)->count());
    }

    public function test_a_catch_up_review_can_be_scheduled(): void
    {
        $charlie = $this->user('charlie@example.com');
        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();
        $schedule = app(ServiceReviewService::class)->setup($stark);

        $at = now()->addDays(3)->setTime(10, 0, 0);

        $this->actingAs($charlie)
            ->post(route('deally.service-reviews.sessions.store', $schedule), [
                'scheduled_at' => $at->format('Y-m-d H:i:s'),
                'notes' => 'Catch-up review',
            ])
            ->assertSessionHas('toast');

        $this->assertTrue(
            ServiceReviewSession::query()
                ->where('schedule_id', $schedule->getKey())
                ->where('scheduled_at', $at->format('Y-m-d H:i:s'))
                ->where('status', ServiceReviewSession::STATUS_SCHEDULED)
                ->exists()
        );
    }

    public function test_ending_a_schedule_removes_future_sessions(): void
    {
        $charlie = $this->user('charlie@example.com');
        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();
        $schedule = app(ServiceReviewService::class)->setup($stark);

        $this->assertSame(3, $schedule->sessions()->count());

        $this->actingAs($charlie)
            ->post(route('deally.service-reviews.end', $schedule))
            ->assertSessionHas('toast');

        $schedule->refresh();

        $this->assertSame(ServiceReviewSchedule::STATUS_ENDED, $schedule->status);
        $this->assertSame(0, $schedule->sessions()->count());
        $this->assertNull($stark->fresh()->activeServiceReview());
    }

    public function test_a_missed_session_marks_the_customer_critical_and_at_risk(): void
    {
        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();
        $schedule = app(ServiceReviewService::class)->setup($stark);

        ServiceReviewSession::query()->create([
            'schedule_id' => $schedule->getKey(),
            'scheduled_at' => now()->subDay(),
            'status' => ServiceReviewSession::STATUS_SCHEDULED,
        ]);

        $assessed = app(RiskService::class)->assess($stark);

        $this->assertSame(RiskService::TIER_CRITICAL, $assessed['tier']);
        $this->assertTrue($assessed['at_risk']);
        $this->assertSame(1, $assessed['missed_sessions']);
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
