<?php

namespace SaasFoundation\Policies;

use SaasFoundation\Models\Project;
use SaasFoundation\Models\User;
use SaasFoundation\Services\Tenancy\TenantContext;

class ProjectPolicy
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

        return $user->hasPermissionInTenant('view_projects', $tenant);
    }

    public function view(User $user, Project $project): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $project->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->hasPermissionInTenant('view_projects', $tenant);
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

        return $user->hasPermissionInTenant('manage_projects', $tenant);
    }

    public function update(User $user, Project $project): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $project->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->hasPermissionInTenant('manage_projects', $tenant);
    }

    public function delete(User $user, Project $project): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $project->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->hasPermissionInTenant('manage_projects', $tenant);
    }
}
