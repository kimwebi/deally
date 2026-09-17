<?php

namespace SaasFoundation\Http\Controllers\Central;

use Illuminate\Http\Request;
use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Models\Subscription;

class CentralSubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $subscriptions = Subscription::query()
            ->with(['plan', 'tenant'])
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', $request->string('status'));
            })
            ->when($request->filled('plan_id'), function ($query) use ($request): void {
                $query->where('plan_id', $request->integer('plan_id'));
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search');
                $query->whereHas('tenant', function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('central.subscriptions.index', compact('subscriptions'));
    }

    public function show(Subscription $subscription)
    {
        $subscription->load(['plan', 'plan.features', 'items.feature']);

        $tenant = $subscription->tenant;

        return view('central.subscriptions.show', compact('subscription', 'tenant'));
    }
}
