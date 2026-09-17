<?php

namespace SaasFoundation\Policies;

use SaasFoundation\Models\Membership;
use SaasFoundation\Models\User;
use SaasFoundation\Services\Authorization\PermissionResolver;
use SaasFoundation\Services\Tenancy\TenantContext;

class MembershipPolicy
{
    public function __construct(
        protected PermissionResolver $resolver,
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

        return $user->hasPermissionInTenant('view_members', $tenant);
    }

    public function view(User $user, Membership $membership): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant) {
            return false;
        }

        if ($membership->user_id === $user->id && $membership->tenant_id === $tenant->id) {
            return true;
        }

        return $user->hasPermissionInTenant('view_members', $tenant);
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

        return $user->hasPermissionInTenant('manage_members', $tenant);
    }

    public function update(User $user, Membership $membership): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $membership->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->hasPermissionInTenant('manage_members', $tenant);
    }

    public function delete(User $user, Membership $membership): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $membership->tenant_id !== $tenant->id) {
            return false;
        }

        if ($membership->user_id === $user->id) {
            return true;
        }

        return $user->hasPermissionInTenant('manage_members', $tenant);
    }

    public function assignRole(User $user, Membership $membership): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $membership->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->hasPermissionInTenant('manage_roles', $tenant);
    }
}
