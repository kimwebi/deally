<?php

namespace SaasFoundation\Services\Billing;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use SaasFoundation\Events\SubscriptionCancelled;
use SaasFoundation\Events\SubscriptionChanged;
use SaasFoundation\Events\SubscriptionCreated;
use SaasFoundation\Models\Plan;
use SaasFoundation\Models\Subscription;
use SaasFoundation\Models\SubscriptionItem;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Billing\Contracts\BillingProviderInterface;

class BillingService
{
    public function __construct(
        protected BillingProviderInterface $provider,
        protected UsageTracker $usageTracker,
    ) {}

    public function createSubscription(Tenant $tenant, Plan $plan, array $options = []): Subscription
    {
        $existing = $this->getActiveSubscription($tenant);

        if ($existing !== null) {
            return $this->changePlan($existing, $plan);
        }

        $trialEndsAt = null;

        if ($plan->trial_days > 0) {
            $trialEndsAt = now()->addDays($plan->trial_days);
        }

        $startsAt = $options['starts_at'] ?? now();
        $status = $trialEndsAt ? Subscription::STATUS_TRIALING : Subscription::STATUS_ACTIVE;

        $subscription = DB::transaction(function () use ($tenant, $plan, $options, $trialEndsAt, $startsAt, $status) {
            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => $status,
                'trial_ends_at' => $trialEndsAt,
                'starts_at' => $startsAt,
                'ends_at' => $options['ends_at'] ?? null,
                'metadata' => $options['metadata'] ?? [],
            ]);

            $this->createSubscriptionItems($subscription, $plan);

            return $subscription;
        });

        $subscription->load('plan', 'items.feature');

        event(new SubscriptionCreated($subscription, $tenant));

        return $subscription;
    }

    public function cancelSubscription(Subscription $subscription): Subscription
    {
        $tenant = Tenant::findOrFail($subscription->tenant_id);

        $subscription->update([
            'status' => Subscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $subscription->items()->update(['is_active' => false]);

        event(new SubscriptionCancelled($subscription, $tenant));

        return $subscription->fresh('plan', 'items.feature');
    }

    public function resumeSubscription(Subscription $subscription): Subscription
    {
        if ($subscription->status !== Subscription::STATUS_CANCELLED) {
            return $subscription;
        }

        $subscription->update([
            'status' => Subscription::STATUS_ACTIVE,
            'cancelled_at' => null,
        ]);

        $subscription->items()->update(['is_active' => true]);

        return $subscription->fresh('plan', 'items.feature');
    }

    public function changePlan(Subscription $subscription, Plan $newPlan): Subscription
    {
        $tenant = Tenant::findOrFail($subscription->tenant_id);
        $oldPlan = $subscription->plan;

        $subscription->update([
            'plan_id' => $newPlan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'trial_ends_at' => null,
            'cancelled_at' => null,
        ]);

        $this->syncSubscriptionItems($subscription, $newPlan);

        event(new SubscriptionChanged($subscription, $tenant, $oldPlan, $newPlan));

        return $subscription->fresh('plan', 'items.feature');
    }

    public function getActiveSubscription(Tenant $tenant): ?Subscription
    {
        return $tenant->subscriptions()
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
            ->with('plan', 'items.feature')
            ->latest()
            ->first();
    }

    public function getPlan(string $slug): ?Plan
    {
        return Plan::where('slug', $slug)->with('features')->first();
    }

    public function getActivePlans(): Collection
    {
        return Plan::active()->ordered()->with('features')->get();
    }

    public function hasFeatureAccess(Tenant $tenant, string $featureSlug): bool
    {
        return $tenant->hasFeature($featureSlug);
    }

    public function getFeatureLimit(Tenant $tenant, string $featureSlug): ?int
    {
        $subscription = $this->getActiveSubscription($tenant);

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

    public function getCurrentUsage(Tenant $tenant, string $featureSlug, ?string $period = null): int
    {
        return $this->usageTracker->getCurrent($tenant, $featureSlug, $period);
    }

    public function incrementUsage(Tenant $tenant, string $featureSlug, int $amount = 1): void
    {
        $this->usageTracker->increment($tenant, $featureSlug, $amount);
    }

    public function decrementUsage(Tenant $tenant, string $featureSlug, int $amount = 1): void
    {
        $this->usageTracker->decrement($tenant, $featureSlug, $amount);
    }

    public function getRemainingUsage(Tenant $tenant, string $featureSlug): ?int
    {
        return $this->usageTracker->getRemaining($tenant, $featureSlug);
    }

    protected function createSubscriptionItems(Subscription $subscription, Plan $plan): void
    {
        $features = $plan->features;

        foreach ($features as $feature) {
            $quota = $feature->pivot->value ?? 'unlimited';

            SubscriptionItem::create([
                'subscription_id' => $subscription->id,
                'feature_id' => $feature->id,
                'quantity' => $quota,
                'is_active' => true,
            ]);
        }
    }

    protected function syncSubscriptionItems(Subscription $subscription, Plan $newPlan): void
    {
        $subscription->items()->update(['is_active' => false]);

        foreach ($newPlan->features as $feature) {
            $quota = $feature->pivot->value ?? 'unlimited';

            SubscriptionItem::updateOrCreate(
                [
                    'subscription_id' => $subscription->id,
                    'feature_id' => $feature->id,
                ],
                [
                    'quantity' => $quota,
                    'is_active' => true,
                ]
            );
        }
    }
}
