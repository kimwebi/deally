<?php

namespace SaasFoundation\Http\Controllers\Central;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Models\Plan;
use SaasFoundation\Services\Billing\FeatureManager;
use SaasFoundation\Services\Billing\PlanManager;

class CentralPlanController extends Controller
{
    public function __construct(
        protected PlanManager $planManager,
        protected FeatureManager $featureManager
    ) {}

    public function index()
    {
        $plans = $this->planManager->getAll();

        return view('central.plans.index', compact('plans'));
    }

    public function create()
    {
        $features = $this->featureManager->getAll();

        return view('central.plans.create', compact('features'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', 'unique:plans,slug'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'billing_interval' => ['required', 'in:monthly,yearly,one-time'],
            'trial_days' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => ['nullable', 'string'],
        ]);

        $plan = $this->planManager->create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'currency' => $validated['currency'],
            'billing_interval' => $validated['billing_interval'],
            'trial_days' => $validated['trial_days'] ?? 0,
            'is_active' => $request->boolean('is_active'),
            'is_default' => $request->boolean('is_default'),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        if (! empty($validated['features'])) {
            $syncData = [];
            foreach ($validated['features'] as $featureId => $quota) {
                if ($quota !== null && $quota !== '') {
                    $syncData[$featureId] = ['quota' => $quota];
                }
            }
            $plan->features()->sync($syncData);
        }

        if ($request->boolean('is_default')) {
            $this->planManager->setDefault($plan);
        }

        return redirect()
            ->route('central.plans.index')
            ->with('success', "Plan '{$plan->name}' created.");
    }

    public function show(Plan $plan)
    {
        $plan->load(['features', 'subscriptions']);

        return view('central.plans.edit', compact('plan'));
    }

    public function edit(Plan $plan)
    {
        $plan->load('features');
        $features = $this->featureManager->getAll();

        return view('central.plans.edit', compact('plan', 'features'));
    }

    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:plans,slug,'.$plan->id],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'billing_interval' => ['required', 'in:monthly,yearly,one-time'],
            'trial_days' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => ['nullable', 'string'],
        ]);

        $this->planManager->update($plan, [
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'currency' => $validated['currency'],
            'billing_interval' => $validated['billing_interval'],
            'trial_days' => $validated['trial_days'] ?? 0,
            'is_active' => $request->boolean('is_active'),
            'is_default' => $request->boolean('is_default'),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        if (array_key_exists('features', $validated)) {
            $syncData = [];
            foreach ($validated['features'] as $featureId => $quota) {
                if ($quota !== null && $quota !== '') {
                    $syncData[$featureId] = ['quota' => $quota];
                }
            }
            $plan->features()->sync($syncData);
        }

        if ($request->boolean('is_default')) {
            $this->planManager->setDefault($plan);
        }

        return redirect()
            ->route('central.plans.index')
            ->with('success', "Plan '{$plan->name}' updated.");
    }

    public function setDefault(Plan $plan)
    {
        $this->planManager->setDefault($plan);

        return back()->with('success', "Plan '{$plan->name}' set as default.");
    }

    public function destroy(Plan $plan)
    {
        if (! $this->planManager->delete($plan)) {
            return back()->with('error', 'Plan cannot be deleted because it has subscriptions.');
        }

        return back()->with('success', "Plan '{$plan->name}' deleted.");
    }
}
