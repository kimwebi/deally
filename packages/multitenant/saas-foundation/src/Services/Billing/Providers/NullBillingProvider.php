<?php

namespace SaasFoundation\Services\Billing\Providers;

use SaasFoundation\Models\Plan;
use SaasFoundation\Models\Subscription;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Billing\Contracts\BillingProviderInterface;

class NullBillingProvider implements BillingProviderInterface
{
    public function createCustomer(Tenant $tenant): mixed
    {
        return null;
    }

    public function createSubscription(Tenant $tenant, Plan $plan, array $options = []): mixed
    {
        return null;
    }

    public function cancelSubscription(Subscription $subscription): mixed
    {
        return null;
    }

    public function resumeSubscription(Subscription $subscription): mixed
    {
        return null;
    }

    public function charge(Tenant $tenant, int $amount, string $currency, array $options = []): mixed
    {
        return false;
    }

    public function refund(string $chargeId, ?int $amount = null): mixed
    {
        return false;
    }
}
