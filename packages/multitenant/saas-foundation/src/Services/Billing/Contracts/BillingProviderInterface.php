<?php

namespace SaasFoundation\Services\Billing\Contracts;

use SaasFoundation\Models\Plan;
use SaasFoundation\Models\Subscription;
use SaasFoundation\Models\Tenant;

interface BillingProviderInterface
{
    public function createCustomer(Tenant $tenant): mixed;

    public function createSubscription(Tenant $tenant, Plan $plan, array $options = []): mixed;

    public function cancelSubscription(Subscription $subscription): mixed;

    public function resumeSubscription(Subscription $subscription): mixed;

    public function charge(Tenant $tenant, int $amount, string $currency, array $options = []): mixed;

    public function refund(string $chargeId, ?int $amount = null): mixed;
}
