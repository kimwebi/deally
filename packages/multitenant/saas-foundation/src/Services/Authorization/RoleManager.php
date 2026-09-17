<?php

namespace SaasFoundation\Services\Authorization;

use Illuminate\Database\Eloquent\Collection;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Permission;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;

class RoleManager
{
    public function createRole(string $name, ?string $tenantId = null, array $permissions = []): Role
    {
        $role = Role::create([
            'name' => $name,
            'tenant_id' => $tenantId,
        ]);

        if (! empty($permissions)) {
            $permissionModels = Permission::whereIn('id', $permissions)->get();
            $role->permissions()->sync($permissionModels);
        }

        return $role->load('permissions');
    }

    public function updateRole(Role $role, array $data): Role
    {
        $role->update($data);

        if (array_key_exists('permissions', $data)) {
            $role->permissions()->sync($data['permissions']);
        }

        return $role->load('permissions');
    }

    public function deleteRole(Role $role): bool
    {
        $role->memberships()->detach();
        $role->permissions()->detach();

        return $role->delete();
    }

    public function getRoles(?string $tenantId = null): Collection
    {
        $query = Role::with('permissions');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get();
    }

    public function createPermission(string $name, string $groupName, ?string $description = null): Permission
    {
        return Permission::create([
            'name' => $name,
            'group_name' => $groupName,
            'description' => $description,
        ]);
    }

    public function getPermissions(): Collection
    {
        return Permission::all();
    }

    public function getPermissionsByGroup(): array
    {
        return Permission::all()->groupBy('group_name')->map(function ($group) {
            return $group->values();
        })->toArray();
    }

    public function assignRoleToMembership(Membership $membership, Role $role): Membership
    {
        $membership->assignRole($role);

        return $membership->load('roles');
    }

    public function removeRoleFromMembership(Membership $membership, Role $role): Membership
    {
        $membership->removeRole($role);

        return $membership->load('roles');
    }

    public function syncRolesForMembership(Membership $membership, array $roleIds): void
    {
        $membership->roles()->sync($roleIds);
    }

    public function userHasRole(User $user, string $roleSlug, ?Tenant $tenant = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $query = $user->memberships()->whereHas('roles', function ($query) use ($roleSlug) {
            $query->where('slug', $roleSlug);
        });

        if ($tenant !== null) {
            $query->where('tenant_id', $tenant->id);
        }

        return $query->exists();
    }

    public function userHasPermission(User $user, string $permissionSlug, ?Tenant $tenant = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $query = $user->memberships()->whereHas('roles.permissions', function ($query) use ($permissionSlug) {
            $query->where('slug', $permissionSlug);
        });

        if ($tenant !== null) {
            $query->where('tenant_id', $tenant->id);
        }

        return $query->exists();
    }

    public function membershipHasPermission(Membership $membership, string $permissionSlug): bool
    {
        return $membership->roles()
            ->whereHas('permissions', function ($query) use ($permissionSlug) {
                $query->where('slug', $permissionSlug);
            })
            ->exists();
    }

    public function createDefaultRoles(Tenant $tenant): void
    {
        $owner = Role::create([
            'name' => 'Owner',
            'slug' => 'owner',
            'tenant_id' => $tenant->id,
            'is_system' => true,
        ]);

        $admin = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
            'tenant_id' => $tenant->id,
            'is_system' => true,
        ]);

        $member = Role::create([
            'name' => 'Member',
            'slug' => 'member',
            'tenant_id' => $tenant->id,
            'is_system' => true,
        ]);

        $viewer = Role::create([
            'name' => 'Viewer',
            'slug' => 'viewer',
            'tenant_id' => $tenant->id,
            'is_system' => true,
        ]);

        $allPermissions = Permission::pluck('id')->toArray();

        $owner->permissions()->sync($allPermissions);

        $adminPermissions = Permission::where('slug', '!=', 'manage_billing')
            ->where('slug', '!=', 'manage_subscription')
            ->pluck('id')
            ->toArray();

        $admin->permissions()->sync($adminPermissions);

        $memberPermissions = Permission::where('group_name', '!=', 'billing')
            ->where('group_name', '!=', 'settings')
            ->where('slug', '!=', 'manage_users')
            ->pluck('id')
            ->toArray();

        $member->permissions()->sync($memberPermissions);

        $viewerPermissionSlugs = [
            'view_projects',
            'view_members',
            'view_audit_logs',
        ];

        $viewerPermissions = Permission::whereIn('slug', $viewerPermissionSlugs)->pluck('id')->toArray();

        $viewer->permissions()->sync($viewerPermissions);
    }

    public function getSystemRoles(): Collection
    {
        return Role::where('is_system', true)
            ->with('permissions')
            ->get();
    }
}
