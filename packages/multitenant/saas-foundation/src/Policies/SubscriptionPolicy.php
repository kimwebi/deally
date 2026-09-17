<?php

namespace SaasFoundation\Policies;

use SaasFoundation\Models\Subscription;
use SaasFoundation\Models\User;
use SaasFoundation\Services\Tenancy\TenantContext;

class SubscriptionPolicy
{
    public function __construct(
        protected TenantContext $tenantContext,
    ) {}

    public function viewAny(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant) {
            return false;
        }

        return $user->hasPermissionInTenant('view_subscription', $tenant);
    }

    public function view(User $user, Subscription $subscription): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $subscription->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->hasPermissionInTenant('view_subscription', $tenant);
    }

    public function create(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant) {
            return false;
        }

        return $user->hasPermissionInTenant('manage_subscription', $tenant);
    }

    public function update(User $user, Subscription $subscription): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $subscription->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->hasPermissionInTenant('manage_subscription', $tenant);
    }

    public function cancel(User $user, Subscription $subscription): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $subscription->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->hasPermissionInTenant('manage_subscription', $tenant);
    }

    public function resume(User $user, Subscription $subscription): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $subscription->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->hasPermissionInTenant('manage_subscription', $tenant);
    }
}
