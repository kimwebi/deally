<?php

namespace Deally\Pipeline\Http\Controllers;

use Deally\Calls\Models\Call;
use Deally\Core\Http\Controllers\Controller;
use Deally\Core\Models\Team;
use Deally\Core\Models\User;
use Deally\Core\Services\Seat;
use Deally\Pipeline\Models\Customer;
use Deally\Pipeline\Services\RiskService;
use Deally\Pipeline\Services\ServiceReviewService;
use Deally\Tasks\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CustomerController extends Controller
{
    public function index()
    {
        $this->authorizeDeally('deally.customers.view');

        $customers = $this->scopeToSeat(Customer::query())
            ->withCount(['opportunities as open_deals_count' => fn ($query) => $query->where('stage', '!=', 'lost')])
            ->withSum(['opportunities as open_value' => fn ($query) => $query->where('stage', '!=', 'lost')], 'value')
            ->orderBy('company')
            ->get();

        return view('pipeline::pages.customers.index', [
            'customers' => $customers,
            'ownerNames' => $this->ownerNames($customers->pluck('owner_user_id')),
            'teamNames' => $this->teamNames($customers->pluck('team_id')),
            'canManage' => $this->deallyCan('deally.customers.manage'),
        ]);
    }

    public function show(Customer $customer, RiskService $risk, ServiceReviewService $reviews)
    {
        $this->authorizeDeally('deally.customers.view');
        $this->authorizeSeatRecord($customer);

        $deals = $customer->opportunities()->orderByDesc('value')->get();

        $calls = $this->scopeToSeat(Call::query())
            ->where('company', $customer->company)
            ->with('opportunity')
            ->orderByDesc('date')
            ->get();

        $tasks = $this->scopeToSeat(Task::query())
            ->where('linked_company', $customer->company)
            ->orderByRaw("CASE WHEN status = 'closed' THEN 1 ELSE 0 END")
            ->orderByDesc('created_at')
            ->get();

        $schedule = $customer->activeServiceReview();

        return view('pipeline::pages.customers.show', [
            'customer' => $customer,
            'deals' => $deals,
            'openDealsCount' => $deals->where('stage', '!=', 'lost')->count(),
            'openValue' => $deals->where('stage', '!=', 'lost')->sum('value'),
            'calls' => $calls,
            'tasks' => $tasks,
            'contacts' => $customer->contacts()->get(),
            'schedule' => $schedule,
            'srUpcoming' => $schedule !== null ? $reviews->upcomingSessions($schedule) : collect(),
            'srMissed' => $schedule !== null ? $reviews->missedSessions($schedule) : collect(),
            'srHeld' => $schedule !== null ? $reviews->heldSessions($schedule) : collect(),
            'srNext' => $schedule !== null ? $reviews->nextSession($schedule) : null,
            'risk' => $risk->assess($customer),
            'ownerNames' => $this->ownerNames(
                collect([$customer->owner_user_id])->merge($calls->pluck('owner_user_id'))
            ),
            'teamNames' => $this->teamNames(collect([$customer->team_id])),
            'ownerOptions' => $this->ownerAssignmentOptions(),
            'canManage' => $this->deallyCan('deally.customers.manage'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeDeally('deally.customers.manage');

        $data = $request->validate([
            'company' => ['required', 'string'],
            'contact_name' => ['nullable', 'string'],
            'contact_title' => ['nullable', 'string'],
        ]);

        $customer = Customer::query()->create($data + [
            'owner_user_id' => auth()->id(),
            'team_id' => $this->teamIdForMember((int) auth()->id()),
        ]);

        return redirect()->route('deally.customers.show', $customer)
            ->with('toast', "Customer added — you own the {$customer->company} account.");
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorizeDeally('deally.customers.manage');
        $this->authorizeSeatRecord($customer);

        $data = $request->validate([
            'contact_name' => ['nullable', 'string'],
            'contact_title' => ['nullable', 'string'],
            'owner_user_id' => ['nullable', 'integer'],
        ]);

        $customer->update([
            'contact_name' => $data['contact_name'] ?? null,
            'contact_title' => $data['contact_title'] ?? null,
        ]);

        $target = $data['owner_user_id'] !== null ? (int) $data['owner_user_id'] : null;

        if ($target !== null && $target !== (int) $customer->owner_user_id) {
            if (! in_array($target, $this->assignmentTargetIds(), true)) {
                return back()->withErrors([
                    'owner_user_id' => 'That user is not an assignable owner for this account.',
                ])->withInput();
            }

            // The team always follows the owning agent.
            $customer->update([
                'owner_user_id' => $target,
                'team_id' => $this->teamIdForMember($target),
            ]);

            $name = User::query()->find($target)?->name;

            $toast = "Account handed off to {$name}.";
        } else {
            $toast = "{$customer->company} account updated.";
        }

        return back()->with('toast', $toast);
    }

    /**
     * User ids the member may hand an account off to. Owners, admins and
     * solutions leads may assign any tenant member; everyone else is limited
     * to the suggested pool — the agents sharing their team.
     *
     * @return array<int, int>
     */
    protected function assignmentTargetIds(): array
    {
        $ids = $this->seatUserIds();

        if ($ids === null) {
            return array_map('intval', $this->tenantMemberUserIds());
        }

        return Seat::suggestedOwnerIds($this->deallyUser());
    }

    /**
     * Assignee options for the change-owner control, filtered to the targets
     * the member is allowed to assign to.
     *
     * @return Collection<int, string>
     */
    protected function ownerAssignmentOptions(): Collection
    {
        $ids = $this->assignmentTargetIds();

        return $this->tenantMembershipOptions()
            ->filter(fn ($name, $id): bool => in_array((int) $id, $ids, true));
    }

    /**
     * Display names for customer owners (central users).
     *
     * @return Collection<int, string>
     */
    protected function ownerNames(Collection $ids): Collection
    {
        $ids = $ids->map(fn ($id): int => (int) $id)->unique()->values()->filter();

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::query()->whereIn('id', $ids)->pluck('name', 'id');
    }

    /**
     * Display names for customer teams (central teams).
     *
     * @return Collection<int, string>
     */
    protected function teamNames(Collection $ids): Collection
    {
        $ids = $ids->map(fn ($id): int => (int) $id)->unique()->values()->filter();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Team::query()->whereIn('id', $ids)->pluck('name', 'id');
    }
}
