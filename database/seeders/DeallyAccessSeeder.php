<?php

namespace Database\Seeders;

use Deally\Core\Models\Team;
use Illuminate\Database\Seeder;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Permission;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;

class DeallyAccessSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPermissions();
        $this->seedRoles();
        $this->retireTenantAdminRole();
        $this->assignDemoSeats();
        $this->seedTeams();
    }

    protected function seedPermissions(): void
    {
        $permissions = [
            ['deally.workspace.view', 'View the workspace home'],
            ['deally.pipeline.view', 'View the pipeline'],
            ['deally.pipeline.manage', 'Create and update opportunities'],
            ['deally.calls.view', 'View calls and transcripts'],
            ['deally.calls.manage', 'Run, end and annotate calls'],
            ['deally.tasks.view', 'View tasks'],
            ['deally.tasks.manage', 'Create and close tasks'],
            ['deally.proposals.view', 'View proposals'],
            ['deally.proposals.manage', 'Create proposals'],
            ['deally.kb.view', 'View the knowledge base'],
            ['deally.kb.manage', 'Manage knowledge base entries'],
            ['deally.reporting.view', 'View team reporting'],
            ['deally.activity.view', 'View the activity feed'],
            ['deally.settings.view', 'View account settings'],
            ['deally.settings.manage', 'Manage tenant settings'],
            ['deally.team.view', 'View teams and members'],
            ['deally.team.manage', 'Manage teams and members'],
        ];

        foreach ($permissions as [$slug, $description]) {
            Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $this->nameFor($slug),
                    'group_name' => 'Deally',
                    'description' => $description,
                ]
            );
        }
    }

    protected function seedRoles(): void
    {
        $agent = [
            'deally.workspace.view',
            'deally.pipeline.view',
            'deally.pipeline.manage',
            'deally.calls.view',
            'deally.calls.manage',
            'deally.tasks.view',
            'deally.tasks.manage',
            'deally.proposals.view',
            'deally.proposals.manage',
            'deally.kb.view',
            'deally.settings.view',
        ];

        $roles = [
            ['slug' => 'sales-agent', 'name' => 'Sales Agent', 'permissions' => $agent],
            ['slug' => 'team-leader', 'name' => 'Team Leader', 'permissions' => array_values(array_unique(array_merge($agent, [
                'deally.reporting.view',
                'deally.activity.view',
                'deally.team.view',
            ])))],
            ['slug' => 'solutions-lead', 'name' => 'Solutions Lead', 'permissions' => [
                'deally.workspace.view',
                'deally.pipeline.view',
                'deally.pipeline.manage',
                'deally.calls.view',
                'deally.tasks.view',
                'deally.proposals.view',
                'deally.kb.view',
                'deally.kb.manage',
                'deally.reporting.view',
                'deally.activity.view',
            ]],
        ];

        foreach ($roles as $role) {
            $model = Role::updateOrCreate(
                ['tenant_id' => null, 'slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'description' => $this->descriptionFor($role['slug']),
                    'is_system' => true,
                ]
            );

            $permissionIds = Permission::whereIn('slug', $role['permissions'])->pluck('id');

            $model->permissions()->sync($permissionIds);
        }

        $this->ensureFoundationRoles();
    }

    /**
     * The tenant-admin role duplicated what the owner role already covers,
     * so it is retired: detach it from every membership and remove it from
     * the global roles table so it never appears in role pickers again.
     */
    protected function retireTenantAdminRole(): void
    {
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'tenant-admin')->first();

        if ($role === null) {
            return;
        }

        $role->memberships()->detach();
        $role->permissions()->detach();
        $role->delete();
    }

    protected function ensureFoundationRoles(): void
    {
        $allIds = Permission::where('group_name', 'Deally')->pluck('id')->all();
        $viewIds = Permission::where('group_name', 'Deally')
            ->where('slug', 'like', '%.view')
            ->whereNotIn('slug', [
                'deally.reporting.view',
                'deally.activity.view',
                'deally.team.view',
            ])
            ->pluck('id')
            ->all();

        foreach ([
            ['slug' => 'owner', 'name' => 'Tenant Owner', 'description' => 'Manages a tenant and everything scoped to it.', 'permissionIds' => $allIds],
            ['slug' => 'admin', 'name' => 'Administrator', 'description' => 'Runs day-to-day tenant administration without sensitive owner operations.', 'permissionIds' => $allIds],
            ['slug' => 'viewer', 'name' => 'Viewer', 'description' => 'Read-only access to a tenant.', 'permissionIds' => $viewIds],
        ] as $role) {
            $model = Role::firstOrCreate(
                ['tenant_id' => null, 'slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'is_system' => true,
                ]
            );

            $model->permissions()->sync($role['permissionIds']);
        }
    }

    protected function assignDemoSeats(): void
    {
        foreach ($this->demoRoster() as ['email' => $email, 'tenant' => $tenantSlug, 'roles' => $roleSlugs]) {
            $this->sync($email, $tenantSlug, $roleSlugs);
        }
    }

    /**
     * Authoritative demo roster for the Deally app.
     *
     * Alice is the only tenant owner and owns every demo instance. Everyone
     * else is staff with per-instance roles, which is what exercises
     * multitenancy: the same person can be an administrator in both instances,
     * an agent in one and a viewer in the other, and so on.
     *
     * @return array<int, array{email: string, tenant: string, roles: list<string>}>
     */
    protected function demoRoster(): array
    {
        return [
            ['email' => 'alice@example.com', 'tenant' => 'acme-corp', 'roles' => ['owner']],
            ['email' => 'alice@example.com', 'tenant' => 'globex', 'roles' => ['owner']],
            ['email' => 'bob@example.com', 'tenant' => 'acme-corp', 'roles' => ['admin']],
            ['email' => 'bob@example.com', 'tenant' => 'globex', 'roles' => ['admin']],
            ['email' => 'charlie@example.com', 'tenant' => 'acme-corp', 'roles' => ['viewer', 'sales-agent']],
            ['email' => 'charlie@example.com', 'tenant' => 'globex', 'roles' => ['viewer']],
            ['email' => 'erica@example.com', 'tenant' => 'acme-corp', 'roles' => ['team-leader']],
            ['email' => 'david@example.com', 'tenant' => 'acme-corp', 'roles' => ['admin']],
            ['email' => 'david@example.com', 'tenant' => 'globex', 'roles' => ['solutions-lead']],
        ];
    }

    /**
     * Ensures the demo user and membership exist, then syncs the exact roles
     * so re-seeding converges on the same state.
     *
     * @param  list<string>  $roleSlugs
     */
    protected function sync(string $email, string $tenantSlug, array $roleSlugs): void
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $this->demoUserName($email),
                'password' => 'password',
                'is_active' => true,
            ]
        );

        $tenant = Tenant::where('slug', $tenantSlug)->first();

        if ($tenant === null) {
            return;
        }

        $membership = Membership::query()->firstOrCreate(
            ['user_id' => $user->id, 'tenant_id' => $tenant->id],
            ['status' => Membership::STATUS_ACTIVE, 'joined_at' => now()]
        );

        $roleIds = Role::whereNull('tenant_id')->whereIn('slug', $roleSlugs)->pluck('id')->all();

        $membership->roles()->sync($roleIds);
    }

    protected function demoUserName(string $email): string
    {
        return match ($email) {
            'alice@example.com' => 'Alice Johnson',
            'bob@example.com' => 'Bob Carter',
            'charlie@example.com' => 'Charlie Lee',
            'erica@example.com' => 'Erica Valdez',
            'david@example.com' => 'David Chen',
            default => str($email)->before('@')->replace('_', ' ')->ucfirst()->toString(),
        };
    }

    protected function seedTeams(): void
    {
        $acme = Tenant::where('slug', 'acme-corp')->first();
        $globex = Tenant::where('slug', 'globex')->first();

        if ($acme) {
            $team = Team::firstOrCreate(
                ['tenant_id' => $acme->id, 'name' => 'East Pod'],
                ['description' => 'Pipeline, onboarding and account growth for the East region.']
            );

            $team->members()->syncWithoutDetaching(
                User::whereIn('email', ['alice@example.com', 'charlie@example.com', 'erica@example.com'])->pluck('id')->all()
            );
        }

        if ($globex) {
            $team = Team::firstOrCreate(
                ['tenant_id' => $globex->id, 'name' => 'Core Pod'],
                ['description' => 'Core account management and renewals.']
            );

            $team->members()->sync(
                User::whereIn('email', ['alice@example.com', 'bob@example.com'])->pluck('id')->all()
            );
        }
    }

    protected function nameFor(string $slug): string
    {
        return str($slug)->after('deally.')->replace('_', ' ')->replace('.', ' | ')->ucfirst()->toString();
    }

    protected function descriptionFor(string $slug): string
    {
        return match ($slug) {
            'sales-agent' => 'Works deals end-to-end within their own pipeline, calls, tasks and proposals.',
            'team-leader' => 'Oversees a team and its reporting; sees what the team sees plus team visibility.',
            'solutions-lead' => 'Handles platform questions and knowledge base governance.',
            default => '',
        };
    }
}
