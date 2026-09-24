<?php

namespace Deally\Pipeline\Services;

use Deally\Pipeline\Models\Customer;
use Deally\Pipeline\Models\ServiceReviewSchedule;
use Deally\Pipeline\Models\ServiceReviewSession;
use Deally\Retention\Services\RetentionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The Service Review engine.
 *
 * A schedule is scoped to one customer, has a cadence (days) and keeps a
 * rolling set of future sessions (UPCOMING_SESSIONS ahead). Sessions are
 * created lazily: whenever one is held or cancelled the series is topped up,
 * so the "next session" never runs out. Ending a schedule is final — future
 * sessions are removed and no further sessions are created.
 */
class ServiceReviewService
{
    /** Cadence (in days) configured per account tier when a schedule starts. */
    public const TIER_CADENCE = [
        'standard' => 30,
        'premium' => 14,
        'enterprise' => 7,
    ];

    protected const UPCOMING_SESSIONS = 3;

    public function tierCadenceDays(?string $tier = null): int
    {
        return self::TIER_CADENCE[$tier ?? app(RetentionService::class)->tier()]
            ?? self::TIER_CADENCE['standard'];
    }

    /**
     * Start a Service Review schedule for a customer at the cadence configured
     * for the account's tier, pre-seeding the upcoming session series.
     */
    public function setup(Customer $customer): ServiceReviewSchedule
    {
        $schedule = ServiceReviewSchedule::query()->create([
            'customer_id' => $customer->getKey(),
            'cadence_days' => $this->tierCadenceDays(),
            'status' => ServiceReviewSchedule::STATUS_ACTIVE,
            'started_at' => now(),
        ]);

        $this->ensureSeries($schedule);

        return $schedule;
    }

    /**
     * @return Collection<int, ServiceReviewSession>
     */
    public function upcomingSessions(ServiceReviewSchedule $schedule): Collection
    {
        return $schedule->sessions()
            ->where('status', ServiceReviewSession::STATUS_SCHEDULED)
            ->where('scheduled_at', '>', now())
            ->get();
    }

    public function nextSession(ServiceReviewSchedule $schedule): ?ServiceReviewSession
    {
        return $schedule->sessions()
            ->where('status', ServiceReviewSession::STATUS_SCHEDULED)
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at')
            ->first();
    }

    /**
     * @return Collection<int, ServiceReviewSession>
     */
    public function missedSessions(ServiceReviewSchedule $schedule): Collection
    {
        return $schedule->sessions()
            ->where('status', ServiceReviewSession::STATUS_SCHEDULED)
            ->where('scheduled_at', '<', now())
            ->get();
    }

    /**
     * @return Collection<int, ServiceReviewSession>
     */
    public function heldSessions(ServiceReviewSchedule $schedule): Collection
    {
        return $schedule->sessions()
            ->where('status', ServiceReviewSession::STATUS_HELD)
            ->get();
    }

    public function reschedule(ServiceReviewSession $session, Carbon $at): ServiceReviewSession
    {
        $session->update(['scheduled_at' => $at->copy()]);

        return $session;
    }

    public function hold(ServiceReviewSession $session, ?string $notes = null): ServiceReviewSession
    {
        $session->update([
            'status' => ServiceReviewSession::STATUS_HELD,
            'notes' => $notes ?: $session->notes,
        ]);

        $this->ensureSeries($session->schedule);

        return $session;
    }

    public function cancel(ServiceReviewSession $session): void
    {
        $session->update(['status' => ServiceReviewSession::STATUS_CANCELLED]);

        // A cancelled future session is a dropped slot — top the series back up.
        $this->ensureSeries($session->schedule);
    }

    /**
     * Schedule a catch-up review so a missed session can be recovered.
     */
    public function catchUp(ServiceReviewSchedule $schedule, Carbon $at, ?string $notes = null): ServiceReviewSession
    {
        return $this->addSession($schedule, $at, $notes ?? 'Catch-up review');
    }

    /**
     * @param  'future'|'regenerate'  $mode
     */
    public function changeCadence(ServiceReviewSchedule $schedule, int $days, string $mode): void
    {
        $schedule->update(['cadence_days' => $days]);

        if ($mode === 'regenerate') {
            // Drop every upcoming slot and restart the series from today at
            // the new cadence.
            $schedule->sessions()
                ->where('status', ServiceReviewSession::STATUS_SCHEDULED)
                ->where('scheduled_at', '>', now())
                ->delete();

            $this->ensureSeries($schedule);
        }
    }

    /**
     * End the schedule: no further future sessions are created and the
     * remaining upcoming slots are removed. Past sessions stay for the record.
     */
    public function end(ServiceReviewSchedule $schedule): void
    {
        $schedule->update([
            'status' => ServiceReviewSchedule::STATUS_ENDED,
            'ended_at' => now(),
        ]);

        $schedule->sessions()
            ->where('status', ServiceReviewSession::STATUS_SCHEDULED)
            ->where('scheduled_at', '>', now())
            ->delete();
    }

    /**
     * Top the upcoming series back up to UPCOMING_SESSIONS, starting after the
     * latest session already on the schedule.
     */
    protected function ensureSeries(ServiceReviewSchedule $schedule): void
    {
        $count = $schedule->sessions()
            ->where('status', ServiceReviewSession::STATUS_SCHEDULED)
            ->where('scheduled_at', '>', now())
            ->count();

        $cursor = $this->seriesCursor($schedule);

        while ($count < self::UPCOMING_SESSIONS) {
            $this->addSession($schedule, $cursor);
            $count++;
            $cursor = $cursor->copy()->addDays((int) $schedule->cadence_days);
        }
    }

    protected function seriesCursor(ServiceReviewSchedule $schedule): Carbon
    {
        // Use a direct query: the sessions() relation is ordered ascending, so
        // ordering desc on top of it would not give us the latest session.
        $last = ServiceReviewSession::query()
            ->where('schedule_id', $schedule->getKey())
            ->orderByDesc('scheduled_at')
            ->first();

        if ($last === null || $last->scheduled_at->isPast()) {
            return now()->addDays((int) $schedule->cadence_days)->startOfMinute();
        }

        return $last->scheduled_at->copy()->addDays((int) $schedule->cadence_days);
    }

    protected function addSession(ServiceReviewSchedule $schedule, Carbon $at, ?string $notes = null): ServiceReviewSession
    {
        return ServiceReviewSession::query()->firstOrCreate(
            [
                'schedule_id' => $schedule->getKey(),
                'scheduled_at' => $at->format('Y-m-d H:i:s'),
            ],
            [
                'status' => ServiceReviewSession::STATUS_SCHEDULED,
                'notes' => $notes,
            ]
        );
    }
}
