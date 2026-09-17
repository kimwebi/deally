<?php

namespace SaasFoundation\Http\Controllers\Central;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Models\Feature;
use SaasFoundation\Services\Billing\FeatureManager;

class CentralFeatureController extends Controller
{
    public function __construct(
        protected FeatureManager $featureManager
    ) {}

    public function index()
    {
        $features = Feature::withCount('plans')->get();

        return view('central.features.index', compact('features'));
    }

    public function create()
    {
        $groups = Feature::pluck('group_name')->unique()->values();

        return view('central.features.create', compact('groups'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', 'unique:features,slug'],
            'description' => ['nullable', 'string'],
            'group_name' => ['required', 'string', 'max:255'],
        ]);

        $feature = $this->featureManager->create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'group_name' => $validated['group_name'],
        ]);

        return redirect()
            ->route('central.features.index')
            ->with('success', "Feature '{$feature->name}' created.");
    }

    public function edit(Feature $feature)
    {
        $groups = Feature::pluck('group_name')->unique()->values();

        return view('central.features.edit', compact('feature', 'groups'));
    }

    public function update(Request $request, Feature $feature)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:features,slug,'.$feature->id],
            'description' => ['nullable', 'string'],
            'group_name' => ['required', 'string', 'max:255'],
        ]);

        $this->featureManager->update($feature, $validated);

        return redirect()
            ->route('central.features.index')
            ->with('success', "Feature '{$feature->name}' updated.");
    }

    public function destroy(Feature $feature)
    {
        $this->featureManager->delete($feature);

        return back()->with('success', "Feature '{$feature->name}' deleted.");
    }
}
