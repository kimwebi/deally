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
        $allSlugs = Permission::where('group_name', 'Deally')->pluck('slug')->all();

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
            ['slug' => 'tenant-admin', 'name' => 'Tenant Administrator', 'permissions' => $allSlugs],
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
    }

    protected function assignDemoSeats(): void
    {
        $this->assign('alice@example.com', 'acme-corp', 'tenant-admin');
        $this->assign('charlie@example.com', 'acme-corp', 'sales-agent');
        $this->assign('bob@example.com', 'globex', 'team-leader');
        $this->assign('alice@example.com', 'globex', 'solutions-lead');
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
                User::whereIn('email', ['alice@example.com', 'charlie@example.com'])->pluck('id')->all()
            );
        }

        if ($globex) {
            $team = Team::firstOrCreate(
                ['tenant_id' => $globex->id, 'name' => 'Core Pod'],
                ['description' => 'Core account management and renewals.']
            );

            $team->members()->syncWithoutDetaching(
                User::whereIn('email', ['bob@example.com', 'alice@example.com'])->pluck('id')->all()
            );
        }
    }

    protected function assign(string $email, string $tenantSlug, string $roleSlug): void
    {
        $user = User::where('email', $email)->first();
        $tenant = Tenant::where('slug', $tenantSlug)->first();

        if ($user === null || $tenant === null) {
            return;
        }

        $membership = Membership::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        $role = Role::whereNull('tenant_id')->where('slug', $roleSlug)->first();

        if ($membership !== null && $role !== null) {
            $membership->roles()->syncWithoutDetaching($role->id);
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
            'tenant-admin' => 'Administers tenant users, teams, roles and settings.',
            default => '',
        };
    }
}
