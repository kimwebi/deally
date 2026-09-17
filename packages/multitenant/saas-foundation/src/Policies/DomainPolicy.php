<?php

namespace SaasFoundation\Policies;

use SaasFoundation\Models\Domain;
use SaasFoundation\Models\User;
use SaasFoundation\Services\Tenancy\TenantContext;

class DomainPolicy
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

        return $user->hasPermissionInTenant('view_domains', $tenant);
    }

    public function view(User $user, Domain $domain): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $domain->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->hasPermissionInTenant('view_domains', $tenant);
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

        return $user->hasPermissionInTenant('manage_domains', $tenant);
    }

    public function update(User $user, Domain $domain): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $domain->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->hasPermissionInTenant('manage_domains', $tenant);
    }

    public function delete(User $user, Domain $domain): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $domain->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->hasPermissionInTenant('manage_domains', $tenant);
    }

    public function verify(User $user, Domain $domain): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $domain->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->hasPermissionInTenant('manage_domains', $tenant);
    }
}
