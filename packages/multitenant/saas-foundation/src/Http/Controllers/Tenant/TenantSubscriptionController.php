<?php

namespace SaasFoundation\Http\Controllers\Tenant;

use Illuminate\Http\Request;
use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Models\Plan;
use SaasFoundation\Models\Subscription;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Billing\BillingService;

class TenantSubscriptionController extends Controller
{
    public function __construct(
        protected BillingService $billingService
    ) {}

    public function index(Tenant $tenant)
    {
        $subscription = $tenant->subscriptions()
            ->with('plan.features', 'items.feature')
            ->latest()
            ->first();

        $plans = Plan::active()->ordered()->with('features')->get();

        $usageRecords = $subscription
            ? $subscription->usageRecords()->with('feature')->latest()->limit(10)->get()
            : collect();

        return view('tenant.subscription.index', compact('tenant', 'subscription', 'plans', 'usageRecords'));
    }

    public function changePlan(Request $request, Tenant $tenant)
    {
        $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $plan = Plan::findOrFail($request->integer('plan_id'));

        $subscription = $tenant->subscriptions()
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
            ->latest()
            ->first();

        if (! $subscription) {
            $this->billingService->createSubscription($tenant, $plan);

            return back()->with('success', "Subscribed to plan '{$plan->name}'.");
        }

        $this->billingService->changePlan($subscription, $plan);

        return back()->with('success', "Plan changed to '{$plan->name}'.");
    }

    public function cancel(Tenant $tenant)
    {
        $subscription = $tenant->subscriptions()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->latest()
            ->first();

        if (! $subscription) {
            return back()->with('error', 'No active subscription to cancel.');
        }

        $this->billingService->cancelSubscription($subscription);

        return back()->with('success', 'Subscription cancelled.');
    }
}
