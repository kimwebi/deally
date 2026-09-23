<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use SaasFoundation\Models\Permission;
use SaasFoundation\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        $allSlugs = Permission::orderBy('slug')->pluck('slug')->all();

        $ownerExcluded = [
            'tenants.delete',
            'tenants.suspend',
            'permissions.assign',
            'billing.manage',
            'security.manage',
            'subscriptions.cancel',
        ];

        $viewerSlugs = array_values(array_filter(
            $allSlugs,
            fn (string $slug): bool => str_ends_with($slug, '.view') || $slug === 'audit.export'
        ));

        $memberSlugs = array_values(array_unique(array_merge($viewerSlugs, [
            'projects.create',
            'projects.update',
            'projects.delete',
            'pages.create',
            'pages.update',
            'pages.delete',
            'memberships.create',
            'invitations.view',
            'invitations.create',
            'usage.view',
        ])));

        $adminSlugs = array_values(array_diff($allSlugs, $ownerExcluded));

        $roles = [
            ['slug' => 'super-admin', 'name' => 'Super Administrator', 'permissions' => $allSlugs],
            ['slug' => 'owner', 'name' => 'Tenant Owner', 'permissions' => $allSlugs],
            ['slug' => 'admin', 'name' => 'Administrator', 'permissions' => $adminSlugs],
            ['slug' => 'member', 'name' => 'Member', 'permissions' => $memberSlugs],
            ['slug' => 'viewer', 'name' => 'Viewer', 'permissions' => $viewerSlugs],
            ['slug' => 'platform-support', 'name' => 'Platform Support', 'permissions' => [
                'tenants.view',
                'tenants.create',
                'audit.view',
            ]],
        ];

        foreach ($roles as $role) {
            $model = Role::updateOrCreate(
                ['tenant_id' => null, 'slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'description' => $this->description($role['slug']),
                    'is_system' => true,
                ]
            );

            $permissionIds = Permission::whereIn('slug', $role['permissions'])->pluck('id');

            $model->permissions()->sync($permissionIds);
        }
    }

    protected function description(string $slug): string
    {
        return match ($slug) {
            'super-admin' => 'Has access to every permission on the platform.',
            'owner' => 'Manages a tenant and everything scoped to it.',
            'admin' => 'Runs day-to-day tenant administration without sensitive owner operations.',
            'member' => 'Works with operational features inside a tenant.',
            'viewer' => 'Read-only access to a tenant.',
            'platform-support' => 'Platform operations: the setup console, tenant provisioning and platform-wide audit logs.',
            default => '',
        };
    }
}
