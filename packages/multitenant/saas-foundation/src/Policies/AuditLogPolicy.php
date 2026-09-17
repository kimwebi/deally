<?php

namespace SaasFoundation\Policies;

use SaasFoundation\Models\AuditLog;
use SaasFoundation\Models\User;
use SaasFoundation\Services\Tenancy\TenantContext;

class AuditLogPolicy
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

        return $user->hasPermissionInTenant('view_audit_logs', $tenant);
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $this->tenantContext->tenant();

        if (! $tenant || $auditLog->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->hasPermissionInTenant('view_audit_logs', $tenant);
    }
}
