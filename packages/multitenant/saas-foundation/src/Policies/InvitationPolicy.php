<?php

namespace SaasFoundation\Policies;

use SaasFoundation\Models\Invitation;
use SaasFoundation\Models\User;
use SaasFoundation\Services\Tenancy\TenantContext;

class InvitationPolicy
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

        return $user->hasPermissionInTenant('manage_invitations', $tenant);
    }

    public function view(User $user, Invitation $invitation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $invitation->tenant_id !== $tenant->id) {
            return false;
        }

        if ($invitation->invited_by === $user->id) {
            return true;
        }

        return $user->hasPermissionInTenant('manage_invitations', $tenant);
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

        return $user->hasPermissionInTenant('manage_invitations', $tenant);
    }

    public function update(User $user, Invitation $invitation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $invitation->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->hasPermissionInTenant('manage_invitations', $tenant);
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $invitation->tenant_id !== $tenant->id) {
            return false;
        }

        if ($invitation->invited_by === $user->id) {
            return true;
        }

        return $user->hasPermissionInTenant('manage_invitations', $tenant);
    }

    public function resend(User $user, Invitation $invitation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $invitation->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->hasPermissionInTenant('manage_invitations', $tenant);
    }

    public function revoke(User $user, Invitation $invitation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $invitation->tenant_id !== $tenant->id) {
            return false;
        }

        if ($invitation->invited_by === $user->id) {
            return true;
        }

        return $user->hasPermissionInTenant('manage_invitations', $tenant);
    }
}
