<?php

namespace SaasFoundation\Services\Tenancy;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SaasFoundation\Models\Feature;
use SaasFoundation\Models\UsageRecord;

class LimitChecker
{
    public function __construct(protected string $featureSlug) {}

    public function limit(): ?int
    {
        $tenant = app(TenantContext::class)->tenant();

        if ($tenant === null) {
            return null;
        }

        $subscription = $tenant->subscriptions()
            ->active()
            ->with('items.feature')
            ->first();

        if ($subscription === null) {
            return null;
        }

        $item = $subscription->items
            ->first(fn ($item) => $item->feature && $item->feature->slug === $this->featureSlug);

        if ($item === null) {
            return null;
        }

        $planFeature = $subscription->plan
            ?->features()
            ->where('features.slug', $this->featureSlug)
            ->first();

        $quota = $planFeature?->pivot->value ?? null;

        if ($quota === null) {
            return null;
        }

        $totalQuota = (int) $quota * $item->quantity;

        return $totalQuota > 0 ? $totalQuota : null;
    }

    public function current(): int
    {
        $tenant = app(TenantContext::class)->tenant();

        if ($tenant === null) {
            return 0;
        }

        $feature = Feature::where('slug', $this->featureSlug)->first();

        if ($feature === null) {
            return 0;
        }

        $period = Carbon::now()->format('Y-m');

        return UsageRecord::where('tenant_id', $tenant->id)
            ->where('feature_id', $feature->id)
            ->where('period', $period)
            ->sum('usage');
    }

    public function remaining(): ?int
    {
        $limit = $this->limit();

        if ($limit === null) {
            return null;
        }

        $current = $this->current();

        return max(0, $limit - $current);
    }

    public function exceeded(): bool
    {
        $limit = $this->limit();

        if ($limit === null) {
            return false;
        }

        return $this->current() >= $limit;
    }

    public function allows(int $amount = 1): bool
    {
        $remaining = $this->remaining();

        if ($remaining === null) {
            return true;
        }

        return $remaining >= $amount;
    }

    public function increment(int $amount = 1): void
    {
        $tenant = app(TenantContext::class)->tenant();

        if ($tenant === null) {
            return;
        }

        $feature = Feature::where('slug', $this->featureSlug)->first();

        if ($feature === null) {
            return;
        }

        $period = Carbon::now()->format('Y-m');

        $filter = [
            'tenant_id' => $tenant->id,
            'feature_id' => $feature->id,
            'period' => $period,
        ];

        $record = DB::table('usage_records')->where($filter)->first();

        if ($record === null) {
            DB::table('usage_records')->insert($filter + [
                'id' => Str::uuid(),
                'usage' => max(0, $amount),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('usage_records')
            ->where($filter)
            ->update([
                'usage' => DB::raw("usage + {$amount}"),
                'updated_at' => now(),
            ]);
    }

    public function decrement(int $amount = 1): void
    {
        $this->increment(-$amount);
    }
}
