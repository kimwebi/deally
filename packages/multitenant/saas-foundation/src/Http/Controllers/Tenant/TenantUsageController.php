<?php

namespace SaasFoundation\Http\Controllers\Tenant;

use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\UsageRecord;

class TenantUsageController extends Controller
{
    public function index(Tenant $tenant)
    {
        $subscription = $tenant->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->with('items.feature')
            ->latest()
            ->first();

        $usageRecords = UsageRecord::forTenant($tenant->id)
            ->with('feature')
            ->latest()
            ->limit(50)
            ->get();

        $usageByFeature = [];

        if ($subscription) {
            foreach ($subscription->items as $item) {
                $feature = $item->feature;

                if (! $feature) {
                    continue;
                }

                $currentPeriod = now()->format('Y-m');

                $record = UsageRecord::forTenant($tenant->id)
                    ->where('feature_id', $feature->id)
                    ->where('period', $currentPeriod)
                    ->first();

                $usageByFeature[] = [
                    'feature' => $feature,
                    'name' => $feature->name,
                    'quantity' => $item->quantity,
                    'usage' => $record ? (int) $record->usage : 0,
                    'percentage' => $item->quantity === null || $item->quantity === 'unlimited' || (int) $item->quantity <= 0
                        ? null
                        : min(100, round(((int) ($record?->usage ?? 0) / (int) $item->quantity) * 100)),
                ];
            }
        }

        $recentUsage = UsageRecord::forTenant($tenant->id)
            ->with('feature')
            ->latest()
            ->limit(10)
            ->get();

        return view('tenant.usage.index', compact('tenant', 'subscription', 'usageRecords', 'usageByFeature', 'recentUsage'));
    }
}
