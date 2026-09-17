<?php

namespace SaasFoundation\Policies;

use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;
use SaasFoundation\Services\Authorization\PermissionResolver;
use SaasFoundation\Services\Tenancy\TenantContext;

class TenantPolicy
{
    public function __construct(
        protected PermissionResolver $resolver,
        protected TenantContext $tenantContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tenant $tenant): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->belongsToTenant($tenant);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Tenant $tenant): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermissionInTenant('manage_tenant', $tenant);
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermissionInTenant('delete_tenant', $tenant);
    }

    public function suspend(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin();
    }

    public function restore(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin();
    }

    public function impersonate(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin();
    }
}
