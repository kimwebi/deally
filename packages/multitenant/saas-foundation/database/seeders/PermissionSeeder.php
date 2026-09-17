<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use SaasFoundation\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'Tenants' => [
                'tenants.view',
                'tenants.create',
                'tenants.update',
                'tenants.delete',
                'tenants.suspend',
                'tenants.restore',
            ],
            'Users' => [
                'users.view',
                'users.create',
                'users.update',
                'users.delete',
                'users.suspend',
            ],
            'Memberships' => [
                'memberships.view',
                'memberships.create',
                'memberships.update',
                'memberships.delete',
            ],
            'Invitations' => [
                'invitations.view',
                'invitations.create',
                'invitations.resend',
                'invitations.cancel',
            ],
            'Roles' => [
                'roles.view',
                'roles.create',
                'roles.update',
                'roles.delete',
            ],
            'Permissions' => [
                'permissions.view',
                'permissions.assign',
            ],
            'Domains' => [
                'domains.view',
                'domains.create',
                'domains.update',
                'domains.delete',
                'domains.verify',
            ],
            'Settings' => [
                'settings.view',
                'settings.update',
            ],
            'Billing' => [
                'billing.view',
                'billing.manage',
            ],
            'Subscriptions' => [
                'subscriptions.view',
                'subscriptions.create',
                'subscriptions.update',
                'subscriptions.cancel',
            ],
            'Features' => [
                'features.view',
                'features.manage',
            ],
            'Usage' => [
                'usage.view',
                'usage.manage',
            ],
            'API' => [
                'api.tokens.view',
                'api.tokens.create',
                'api.tokens.revoke',
            ],
            'Audit' => [
                'audit.view',
                'audit.export',
            ],
            'Security' => [
                'security.view',
                'security.manage',
            ],
            'Projects' => [
                'projects.view',
                'projects.create',
                'projects.update',
                'projects.delete',
            ],
            'Pages' => [
                'pages.view',
                'pages.create',
                'pages.update',
                'pages.delete',
            ],
        ];

        foreach ($permissions as $group => $slugs) {
            foreach ($slugs as $slug) {
                Permission::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $this->permissionName($slug),
                        'group_name' => $group,
                        'description' => $this->permissionDescription($slug, $group),
                    ]
                );
            }
        }
    }

    protected function permissionName(string $slug): string
    {
        [$resource, $action] = $this->parts($slug);

        return Str::title($action).' '.Str::title($resource);
    }

    protected function permissionDescription(string $slug, string $group): string
    {
        [$resource, $action] = $this->parts($slug);

        return "Can {$action} {$group} {$resource}.";
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function parts(string $slug): array
    {
        $segments = explode('.', $slug);
        $action = array_pop($segments);

        return [implode(' ', $segments), $action];
    }
}
