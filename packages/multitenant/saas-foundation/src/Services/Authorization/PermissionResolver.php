<?php

namespace SaasFoundation\Services\Authorization;

use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;

class PermissionResolver
{
    public function resolveForUser(User $user, ?Tenant $tenant = null): array
    {
        if ($user->isSuperAdmin()) {
            return ['*'];
        }

        $query = $user->memberships()
            ->with('roles.permissions')
            ->active();

        if ($tenant !== null) {
            $query->where('tenant_id', $tenant->id);
        }

        $memberships = $query->get();

        $permissions = [];
        $roles = [];

        foreach ($memberships as $membership) {
            foreach ($membership->roles as $role) {
                $roles[$role->slug] = $role->name;

                foreach ($role->permissions as $permission) {
                    $permissions[$permission->slug] = [
                        'name' => $permission->name,
                        'group' => $permission->group_name,
                    ];
                }
            }
        }

        return [
            'permissions' => array_keys($permissions),
            'roles' => array_values($roles),
            'details' => $permissions,
        ];
    }

    public function resolveForMembership(Membership $membership): array
    {
        $roles = $membership->roles()->with('permissions')->get();

        $permissions = [];
        $roleNames = [];

        foreach ($roles as $role) {
            $roleNames[] = $role->name;

            foreach ($role->permissions as $permission) {
                $permissions[$permission->slug] = [
                    'name' => $permission->name,
                    'group' => $permission->group_name,
                ];
            }
        }

        return [
            'permissions' => array_keys($permissions),
            'roles' => $roleNames,
            'details' => $permissions,
        ];
    }

    public function hasPermission(User $user, string $permission, ?Tenant $tenant = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $query = $user->memberships()
            ->whereHas('roles.permissions', function ($q) use ($permission) {
                $q->where('slug', $permission);
            })
            ->active();

        if ($tenant !== null) {
            $query->where('tenant_id', $tenant->id);
        }

        return $query->exists();
    }

    public function hasRole(User $user, string $role, ?Tenant $tenant = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $query = $user->memberships()
            ->whereHas('roles', function ($q) use ($role) {
                $q->where('slug', $role);
            })
            ->active();

        if ($tenant !== null) {
            $query->where('tenant_id', $tenant->id);
        }

        return $query->exists();
    }
}
