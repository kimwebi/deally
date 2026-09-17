<?php

namespace SaasFoundation\Services\Tenancy;

use SaasFoundation\Models\Plan;
use SaasFoundation\Models\Subscription;

class SubscriptionManager
{
    public function active(): bool
    {
        $subscription = $this->subscription();

        if ($subscription === null) {
            return false;
        }

        return $subscription->isActive() || $subscription->isTrialing();
    }

    public function subscription(): ?Subscription
    {
        $tenant = app(TenantContext::class)->tenant();

        if ($tenant === null) {
            return null;
        }

        return $tenant->subscriptions()
            ->with('plan')
            ->whereIn('status', [
                Subscription::STATUS_ACTIVE,
                Subscription::STATUS_TRIALING,
                Subscription::STATUS_PAST_DUE,
            ])
            ->latest()
            ->first();
    }

    public function plan(): ?Plan
    {
        $subscription = $this->subscription();

        return $subscription?->plan;
    }

    public function isTrialing(): bool
    {
        $subscription = $this->subscription();

        if ($subscription === null) {
            return false;
        }

        return $subscription->isTrialing();
    }

    public function trialDaysRemaining(): ?int
    {
        $subscription = $this->subscription();

        if ($subscription === null || ! $subscription->isTrialing()) {
            return null;
        }

        if ($subscription->trial_ends_at === null) {
            return null;
        }

        $days = (int) $subscription->trial_ends_at->diffInDays(now());

        return max(0, $days);
    }
}
