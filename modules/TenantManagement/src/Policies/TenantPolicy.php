<?php

namespace Deally\TenantManagement\Policies;

use Deally\Core\Models\User;
use Deally\TenantManagement\Models\Tenant;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_superadmin || $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin || $user->is_superadmin;
    }

    public function clone(User $user, Tenant $tenant): bool
    {
        return $user->is_superadmin;
    }
}
