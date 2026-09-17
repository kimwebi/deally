<?php

namespace SaasFoundation\Policies;

use SaasFoundation\Models\User;
use SaasFoundation\Services\Authorization\PermissionResolver;
use SaasFoundation\Services\Tenancy\TenantContext;

class UserPolicy
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

    public function view(User $user, User $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->id === $model->id) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant) {
            return false;
        }

        return $user->hasPermissionInTenant('view_members', $tenant)
            && $model->belongsToTenant($tenant);
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

        return $user->hasPermissionInTenant('manage_users', $tenant);
    }

    public function update(User $user, User $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->id === $model->id) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant) {
            return false;
        }

        return $user->hasPermissionInTenant('manage_users', $tenant)
            && $model->belongsToTenant($tenant);
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->id === $model->id) {
            return false;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant) {
            return false;
        }

        return $user->hasPermissionInTenant('manage_users', $tenant)
            && $model->belongsToTenant($tenant);
    }

    public function suspend(User $user, User $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->id === $model->id) {
            return false;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant) {
            return false;
        }

        return $user->hasPermissionInTenant('manage_users', $tenant)
            && $model->belongsToTenant($tenant);
    }

    public function activate(User $user, User $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant) {
            return false;
        }

        return $user->hasPermissionInTenant('manage_users', $tenant)
            && $model->belongsToTenant($tenant);
    }

    public function impersonate(User $user, User $model): bool
    {
        return $user->isSuperAdmin();
    }
}
