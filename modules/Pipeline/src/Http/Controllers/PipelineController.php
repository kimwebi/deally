<?php

namespace Deally\Pipeline\Http\Controllers;

use Deally\Core\Http\Controllers\Controller;
use Deally\Core\Models\User;
use Deally\Pipeline\Models\Customer;
use Deally\Pipeline\Models\Opportunity;
use Deally\Pipeline\Services\RiskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PipelineController extends Controller
{
    public function index(Request $request, RiskService $risk): View
    {
        $this->authorizeDeally('deally.pipeline.view');

        $query = $this->scopeDealsToSeat(Opportunity::query());

        $opportunities = $query
            ->with('customer')
            ->when($request->query('stage'), function ($query, string $stage): void {
                $query->where('stage', $stage);
            })
            ->orderByDesc('value')
            ->get();

        $customers = $opportunities->pluck('customer')->filter()->unique('id');

        $assessments = $customers->isNotEmpty() ? $risk->assessments($customers) : collect();

        $totalValue = $opportunities->sum('value');

        return view('pipeline::pages.pipeline', [
            'opportunities' => $opportunities,
            'totalValue' => $totalValue,
            'stages' => Opportunity::stages(),
            'activeStage' => $request->query('stage'),
            'view' => $request->query('view') === 'board' ? 'board' : 'list',
            'assessments' => $assessments,
            'ownerNames' => $this->ownerNames($opportunities->pluck('customer')->filter()->map->owner_user_id),
            'customerPicker' => $this->customerPickerOptions(),
            'canManage' => $this->deallyCan('deally.pipeline.manage'),
            'srSetupPrompt' => session('service_review_setup_customer'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeDeally('deally.pipeline.manage');

        $data = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'company' => ['nullable', 'string'],
            'contact_name' => ['nullable', 'string'],
            'contact_title' => ['nullable', 'string'],
            'packages' => ['nullable', 'string'],
            'stage' => ['nullable', 'string', 'in:discovery,demo,negotiation,won,lost'],
            'value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $wasCreated = false;

        $customer = $this->resolveCustomer($data, $wasCreated);

        if ($customer === null) {
            return back()->withErrors(['customer_id' => 'Pick an existing customer account for this deal.']);
        }

        // Deals always carry the customer's company so legacy company-based
        // joins (proposals, calls, tasks) keep working.
        $data['company'] = $customer->company;

        Opportunity::create($data + ['customer_id' => $customer->getKey()]);

        $toast = $wasCreated
            ? "Deal created — you own the {$customer->company} account."
            : "Deal attached to {$customer->company}.";

        // A fresh deal for a customer with no active Service Review schedule
        // prompts the user to set one up at the account tier cadence.
        $prompt = $customer->activeServiceReview() === null ? $customer->getKey() : null;

        return back()
            ->with('toast', $toast)
            ->with('service_review_setup_customer', $prompt);
    }

    /**
     * Display names for deal owners (central users).
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
     * Customers the member may attach a new deal to, scoped to their seat.
     *
     * @return Collection<int, string> customer id => company
     */
    protected function customerPickerOptions(): Collection
    {
        return $this->scopeToSeat(Customer::query())->orderBy('company')->pluck('company', 'id');
    }

    /**
     * The customer a new deal belongs to: the picked customer when given
     * (preferred — this is the "customer picker is required" flow), otherwise
     * the legacy find-or-create by company.
     */
    protected function resolveCustomer(array $data, bool &$wasCreated): ?Customer
    {
        if (($data['customer_id'] ?? null) !== null) {
            $customer = Customer::query()->find((int) $data['customer_id']);

            if ($customer === null || ! $this->isCustomerVisible($customer)) {
                return null;
            }

            return $customer;
        }

        $company = $data['company'] ?? null;

        if (blank($company)) {
            return null;
        }

        $customer = Customer::query()->firstOrCreate(
            ['company' => $company],
            [
                'contact_name' => $data['contact_name'] ?? null,
                'contact_title' => $data['contact_title'] ?? null,
                'owner_user_id' => auth()->id(),
                'team_id' => $this->userTeamId(),
            ]
        );

        $wasCreated = $customer->wasRecentlyCreated;

        return $customer;
    }

    protected function isCustomerVisible(Customer $customer): bool
    {
        $ids = $this->seatUserIds();

        if ($ids === null) {
            return true;
        }

        return in_array((int) $customer->owner_user_id, $ids, true);
    }
}
