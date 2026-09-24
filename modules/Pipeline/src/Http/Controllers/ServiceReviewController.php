<?php

namespace Deally\Pipeline\Http\Controllers;

use Deally\Core\Http\Controllers\Controller;
use Deally\Pipeline\Models\Customer;
use Deally\Pipeline\Models\ServiceReviewSchedule;
use Deally\Pipeline\Models\ServiceReviewSession;
use Deally\Pipeline\Services\ServiceReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ServiceReviewController extends Controller
{
    public function setup(Request $request, Customer $customer, ServiceReviewService $reviews): RedirectResponse
    {
        $this->authorizeDeally('deally.customers.manage');
        $this->authorizeSeatRecord($customer);

        if ($customer->activeServiceReview() !== null) {
            return back()->with('toast', 'This customer already has an active Service Review schedule.');
        }

        $reviews->setup($customer);

        return back()->with('toast', "Service Reviews set up for {$customer->company}.");
    }

    public function updateCadence(Request $request, ServiceReviewSchedule $schedule, ServiceReviewService $reviews): RedirectResponse
    {
        $this->authorizeDeally('deally.customers.manage');
        $this->authorizeSeatRecord($schedule->customer);

        $data = $request->validate([
            'cadence_days' => ['required', 'integer', 'min:1', 'max:365'],
            'mode' => ['required', 'string', 'in:future,regenerate'],
        ]);

        $reviews->changeCadence($schedule, (int) $data['cadence_days'], $data['mode']);

        return back()->with('toast', $data['mode'] === 'regenerate'
            ? 'Cadence updated — upcoming reviews were regenerated.'
            : 'Cadence updated — existing upcoming reviews kept.');
    }

    public function rescheduleSession(Request $request, ServiceReviewSession $session, ServiceReviewService $reviews): RedirectResponse
    {
        $this->authorizeDeally('deally.customers.manage');
        $this->authorizeSeatRecord($session->schedule->customer);

        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $at = Carbon::parse($data['scheduled_at']);

        $this->assertNoTimeClash($session->schedule_id, $at, $session->getKey());

        $reviews->reschedule($session, $at);

        return back()->with('toast', 'Review session rescheduled.');
    }

    public function holdSession(Request $request, ServiceReviewSession $session, ServiceReviewService $reviews): RedirectResponse
    {
        $this->authorizeDeally('deally.customers.manage');
        $this->authorizeSeatRecord($session->schedule->customer);

        $reviews->hold($session, $request->input('notes'));

        return back()->with('toast', 'Review session marked as held.');
    }

    public function cancelSession(Request $request, ServiceReviewSession $session, ServiceReviewService $reviews): RedirectResponse
    {
        $this->authorizeDeally('deally.customers.manage');
        $this->authorizeSeatRecord($session->schedule->customer);

        $reviews->cancel($session);

        return back()->with('toast', 'Review session cancelled — it does not count as missed.');
    }

    public function catchUp(Request $request, ServiceReviewSchedule $schedule, ServiceReviewService $reviews): RedirectResponse
    {
        $this->authorizeDeally('deally.customers.manage');
        $this->authorizeSeatRecord($schedule->customer);

        $data = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $at = Carbon::parse($data['scheduled_at']);

        $this->assertNoTimeClash($schedule->getKey(), $at);

        $reviews->catchUp($schedule, $at, $data['notes'] ?? null);

        return back()->with('toast', 'Catch-up review scheduled.');
    }

    public function end(Request $request, ServiceReviewSchedule $schedule, ServiceReviewService $reviews): RedirectResponse
    {
        $this->authorizeDeally('deally.customers.manage');
        $this->authorizeSeatRecord($schedule->customer);

        $reviews->end($schedule);

        return back()->with('toast', 'Service Review schedule ended. No further reviews will be scheduled.');
    }

    protected function assertNoTimeClash(int $scheduleId, Carbon $at, ?int $exceptSessionId = null): void
    {
        $clash = ServiceReviewSession::query()
            ->where('schedule_id', $scheduleId)
            ->when($exceptSessionId !== null, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->where('scheduled_at', $at->format('Y-m-d H:i:s'))
            ->exists();

        if ($clash) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'Another review session is already scheduled for that time.',
            ]);
        }
    }
}
