<?php

namespace SaasFoundation\Services\Billing;

use Illuminate\Database\Eloquent\Collection;
use SaasFoundation\Models\Feature;
use SaasFoundation\Models\Plan;

class PlanManager
{
    public function create(array $data): Plan
    {
        return Plan::create($data);
    }

    public function update(Plan $plan, array $data): Plan
    {
        $plan->update($data);

        return $plan->fresh();
    }

    public function delete(Plan $plan): bool
    {
        if ($plan->subscriptions()->exists()) {
            return false;
        }

        return $plan->delete();
    }

    public function getAll(): Collection
    {
        return Plan::with('features')->ordered()->get();
    }

    public function getActive(): Collection
    {
        return Plan::active()->ordered()->with('features')->get();
    }

    public function getDefault(): ?Plan
    {
        return Plan::where('is_default', true)->first();
    }

    public function setDefault(Plan $plan): void
    {
        Plan::query()->update(['is_default' => false]);

        $plan->update(['is_default' => true]);
    }

    public function addFeature(Plan $plan, Feature $feature, ?string $value = 'unlimited'): Plan
    {
        $plan->features()->syncWithoutDetaching([
            $feature->id => ['quota' => $value],
        ]);

        return $plan->fresh(['features']);
    }

    public function removeFeature(Plan $plan, Feature $feature): Plan
    {
        $plan->features()->detach($feature->id);

        return $plan->fresh(['features']);
    }

    public function syncFeatures(Plan $plan, array $features): Plan
    {
        $syncData = [];

        foreach ($features as $featureId => $value) {
            $syncData[$featureId] = ['quota' => $value];
        }

        $plan->features()->sync($syncData);

        return $plan->fresh(['features']);
    }

    public function getFeatures(Plan $plan): Collection
    {
        return $plan->features;
    }
}
