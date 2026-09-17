<?php

namespace SaasFoundation\Services\Billing;

use Illuminate\Support\Facades\DB;
use SaasFoundation\Models\Feature;
use SaasFoundation\Models\Subscription;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\UsageRecord;

class UsageTracker
{
    public function increment(Tenant $tenant, string $featureSlug, int $amount = 1, ?string $period = null): void
    {
        $period = $period ?? $this->getCurrentPeriod();
        $feature = Feature::where('slug', $featureSlug)->firstOrFail();

        UsageRecord::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'feature_id' => $feature->id,
                'period' => $period,
            ],
            [
                'usage' => DB::raw("usage + {$amount}"),
            ]
        );
    }

    public function decrement(Tenant $tenant, string $featureSlug, int $amount = 1, ?string $period = null): void
    {
        $period = $period ?? $this->getCurrentPeriod();
        $feature = Feature::where('slug', $featureSlug)->firstOrFail();

        $record = UsageRecord::where('tenant_id', $tenant->id)
            ->where('feature_id', $feature->id)
            ->where('period', $period)
            ->first();

        if ($record === null) {
            return;
        }

        $record->update([
            'usage' => max(0, (int) $record->usage - $amount),
        ]);
    }

    public function getCurrent(Tenant $tenant, string $featureSlug, ?string $period = null): int
    {
        $period = $period ?? $this->getCurrentPeriod();

        return $this->resolveCurrentUsage($tenant, $featureSlug, $period);
    }

    public function getRemaining(Tenant $tenant, string $featureSlug, ?string $period = null): ?int
    {
        $limit = $this->resolveLimit($tenant, $featureSlug);

        if ($limit === null) {
            return null;
        }

        $current = $this->getCurrent($tenant, $featureSlug, $period);

        return max(0, $limit - $current);
    }

    public function isExceeded(Tenant $tenant, string $featureSlug, ?string $period = null): bool
    {
        $limit = $this->resolveLimit($tenant, $featureSlug);

        if ($limit === null) {
            return false;
        }

        $current = $this->getCurrent($tenant, $featureSlug, $period);

        return $current >= $limit;
    }

    public function allows(Tenant $tenant, string $featureSlug, int $amount = 1, ?string $period = null): bool
    {
        $limit = $this->resolveLimit($tenant, $featureSlug);

        if ($limit === null) {
            return true;
        }

        $current = $this->getCurrent($tenant, $featureSlug, $period);

        return ($current + $amount) <= $limit;
    }

    public function reset(Tenant $tenant, string $featureSlug, ?string $period = null): void
    {
        $period = $period ?? $this->getCurrentPeriod();
        $feature = Feature::where('slug', $featureSlug)->first();

        if ($feature === null) {
            return;
        }

        UsageRecord::where('tenant_id', $tenant->id)
            ->where('feature_id', $feature->id)
            ->where('period', $period)
            ->update(['usage' => 0]);
    }

    protected function getCurrentPeriod(): string
    {
        return now()->format(config('saas.usage.period_format', 'Y-m'));
    }

    protected function resolveLimit(Tenant $tenant, string $featureSlug): ?int
    {
        $subscription = $tenant->subscriptions()
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
            ->latest()
            ->first();

        if ($subscription === null) {
            return null;
        }

        $item = $subscription->items()
            ->whereHas('feature', function ($query) use ($featureSlug): void {
                $query->where('slug', $featureSlug);
            })
            ->where('is_active', true)
            ->first();

        if ($item === null) {
            return null;
        }

        $quota = $item->quantity;

        if ($quota === null || $quota === 'unlimited' || (int) $quota < 0) {
            return null;
        }

        return (int) $quota;
    }

    protected function resolveCurrentUsage(Tenant $tenant, string $featureSlug, string $period): int
    {
        $feature = Feature::where('slug', $featureSlug)->first();

        if ($feature === null) {
            return 0;
        }

        $record = UsageRecord::where('tenant_id', $tenant->id)
            ->where('feature_id', $feature->id)
            ->where('period', $period)
            ->first();

        return $record ? (int) $record->usage : 0;
    }
}
