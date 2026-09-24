<?php

namespace Deally\Pipeline\Http\Controllers;

use Deally\Calls\Models\Call;
use Deally\Core\Http\Controllers\Controller;
use Deally\Core\Models\User;
use Deally\Core\Services\ActivityLogger;
use Deally\Pipeline\Models\Customer;
use Deally\Pipeline\Models\Opportunity;
use Deally\Pipeline\Services\RiskService;
use Deally\Proposals\Models\Proposal;
use Deally\Retention\Services\RetentionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use SaasFoundation\Models\Activity;

class DealController extends Controller
{
    public function show(Opportunity $opportunity, RiskService $risk, RetentionService $retention): View
    {
        $this->authorizeDeally('deally.pipeline.view');
        $this->authorizeDeal($opportunity);

        $customer = $opportunity->customer;

        $calls = $opportunity->calls()->orderByDesc('date')->get();
        $proposals = Proposal::query()->where('company', $opportunity->company)->orderByDesc('updated_at')->get();
        $events = Activity::query()
            ->where('tenant_id', $this->deallyMembership()?->tenant_id)
            ->where('subject_type', $opportunity->getMorphClass())
            ->where('subject_id', $opportunity->getKey())
            ->orderByDesc('created_at')
            ->get();

        $log = $this->engagementLog($calls, $proposals, $events);
        $assessed = $customer !== null ? $risk->assess($customer) : null;

        return view('pipeline::pages.deals.show', [
            'opportunity' => $opportunity,
            'customer' => $customer,
            'ownerName' => $customer?->owner_user_id !== null
                ? (User::query()->find($customer->owner_user_id)?->name)
                : null,
            'log' => $log,
            'stages' => Opportunity::stages(),
            'assessed' => $assessed,
            'archived' => $retention->isDealArchived($opportunity),
            'possibleLost' => $this->isPossibleLost($opportunity, $assessed),
            'canManage' => $this->deallyCan('deally.pipeline.manage'),
        ]);
    }

    public function updateStage(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorizeDeally('deally.pipeline.manage');
        $this->authorizeDeal($opportunity);

        $data = $request->validate([
            'stage' => ['required', 'string', 'in:discovery,demo,negotiation,won,lost'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $toLost = $data['stage'] === 'lost';

        if ($toLost && blank($data['lost_reason'] ?? null)) {
            throw ValidationException::withMessages([
                'lost_reason' => 'A reason is required when moving a deal to Lost.',
            ]);
        }

        $previous = $opportunity->stage;

        $opportunity->update([
            'stage' => $data['stage'],
            'lost_reason' => $toLost ? $data['lost_reason'] : null,
        ]);

        app(ActivityLogger::class)->log(
            'opportunity.stage',
            'Moved '.ucfirst($previous).' → '.ucfirst($data['stage'])
                .($toLost ? " (reason: {$data['lost_reason']})" : ''),
            ['previous_stage' => $previous, 'stage' => $data['stage'], 'lost_reason' => $data['lost_reason'] ?? null],
            $toLost ? 'warning' : 'info',
            $opportunity
        );

        return back()->with('toast', 'Deal stage updated and logged.');
    }

    public function storeNote(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorizeDeally('deally.pipeline.manage');
        $this->authorizeDeal($opportunity);

        $data = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
        ]);

        app(ActivityLogger::class)->log(
            'opportunity.note',
            $data['note'],
            [],
            'info',
            $opportunity
        );

        return back()->with('toast', 'Note added to the engagement log.');
    }

    /**
     * Merges the deal's calls, proposals and logged events into one
     * chronological feed, newest first.
     *
     * @return Collection<int, array{
     *     type: string,
     *     at: Carbon,
     *     call?: Call,
     *     proposal?: Proposal,
     *     activity?: Activity,
     * }>
     */
    protected function engagementLog($calls, $proposals, $events)
    {
        return collect()
            ->merge($calls->map(fn (Call $call): array => [
                'type' => 'call',
                'at' => $call->date,
                'call' => $call,
            ]))
            ->merge($proposals->map(fn (Proposal $proposal): array => [
                'type' => 'proposal',
                'at' => $proposal->updated_at,
                'proposal' => $proposal,
            ]))
            ->merge($events->map(fn (Activity $event): array => [
                'type' => 'activity',
                'at' => $event->created_at,
                'activity' => $event,
            ]))
            ->sortByDesc(fn (array $row): int => $row['at']->timestamp)
            ->values();
    }

    /**
     * A deal is "possibly lost" when its customer is at risk (Critical or High)
     * and the deal is still open. The flag is read-only on this screen — it is
     * only actioned inside its Review Call task, which this screen links to.
     */
    protected function isPossibleLost(Opportunity $opportunity, ?array $assessed): bool
    {
        if ($assessed === null || ! $assessed['at_risk']) {
            return false;
        }

        return ! in_array($opportunity->stage, ['won', 'lost'], true);
    }

    /**
     * Ownership lives on the customer, so a deal is visible only when its
     * customer is visible to the member's seat.
     */
    protected function authorizeDeal(Opportunity $opportunity): void
    {
        $ids = $this->seatUserIds();

        if ($ids === null) {
            return;
        }

        $visible = Customer::query()
            ->whereIn('owner_user_id', $ids)
            ->whereKey((int) $opportunity->customer_id)
            ->exists();

        abort_unless($visible, 403);
    }
}
