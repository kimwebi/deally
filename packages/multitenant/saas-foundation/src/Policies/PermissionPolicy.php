<?php

namespace SaasFoundation\Policies;

use SaasFoundation\Models\Permission;
use SaasFoundation\Models\User;
use SaasFoundation\Services\Tenancy\TenantContext;

class PermissionPolicy
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

        return $user->hasPermissionInTenant('view_permissions', $tenant);
    }

    public function view(User $user, Permission $permission): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant) {
            return false;
        }

        return $user->hasPermissionInTenant('view_permissions', $tenant);
    }
}
