<?php

namespace SaasFoundation\Services\Billing;

use Illuminate\Database\Eloquent\Collection;
use SaasFoundation\Models\Feature;

class FeatureManager
{
    public function create(array $data): Feature
    {
        return Feature::create($data);
    }

    public function update(Feature $feature, array $data): Feature
    {
        $feature->update($data);

        return $feature->fresh();
    }

    public function delete(Feature $feature): bool
    {
        return $feature->delete();
    }

    public function getAll(): Collection
    {
        return Feature::all();
    }

    public function getByGroup(?string $group = null): array|Collection
    {
        if ($group !== null) {
            return Feature::where('group_name', $group)->get();
        }

        return Feature::all()
            ->groupBy('group_name')
            ->toArray();
    }

    public function findBySlug(string $slug): ?Feature
    {
        return Feature::where('slug', $slug)->first();
    }
}
