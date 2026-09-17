<?php

namespace SaasFoundation\Policies;

use SaasFoundation\Models\Role;
use SaasFoundation\Models\User;
use SaasFoundation\Services\Authorization\PermissionResolver;
use SaasFoundation\Services\Tenancy\TenantContext;

class RolePolicy
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

        return $user->hasPermissionInTenant('view_roles', $tenant);
    }

    public function view(User $user, Role $role): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant) {
            return false;
        }

        return $role->tenant_id === $tenant->id
            && $user->hasPermissionInTenant('view_roles', $tenant);
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

        return $user->hasPermissionInTenant('manage_roles', $tenant);
    }

    public function update(User $user, Role $role): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $role->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->hasPermissionInTenant('manage_roles', $tenant);
    }

    public function delete(User $user, Role $role): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $role->tenant_id !== $tenant->id) {
            return false;
        }

        if ($role->isSystem()) {
            return false;
        }

        return $user->hasPermissionInTenant('manage_roles', $tenant);
    }

    public function assign(User $user, Role $role): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $role->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->hasPermissionInTenant('manage_roles', $tenant);
    }
}
