<?php

namespace SaasFoundation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Permission;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;

class TenantCreateCommand extends Command
{
    protected $signature = 'tenant:create
                            {--name= : The tenant name (required)}
                            {--slug= : The tenant slug (optional, generated from name)}
                            {--status=active : The initial tenant status}
                            {--email= : Email of the owner user to add}';

    protected $description = 'Create a new tenant';

    public function handle(): int
    {
        $name = $this->option('name');
        $status = $this->option('status');

        if ($name === null || $name === '') {
            $this->error('The --name option is required.');

            return self::FAILURE;
        }

        $allowedStatuses = [
            Tenant::STATUS_PENDING,
            Tenant::STATUS_PROVISIONING,
            Tenant::STATUS_ACTIVE,
            Tenant::STATUS_TRIAL,
            Tenant::STATUS_SUSPENDED,
            Tenant::STATUS_INACTIVE,
            Tenant::STATUS_ARCHIVED,
        ];

        if (! in_array($status, $allowedStatuses, true)) {
            $this->error("Invalid status '{$status}'. Allowed: ".implode(', ', $allowedStatuses));

            return self::FAILURE;
        }

        $slug = $this->option('slug') ?? Str::slug($name);

        if (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $this->error("A tenant with slug '{$slug}' already exists.");

            return self::FAILURE;
        }

        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'status' => $status,
        ]);

        $email = $this->option('email');

        if ($email !== null && $email !== '') {
            $this->addOwner($tenant, $email);
        }

        $this->info("Tenant '{$tenant->name}' created.");
        $this->table(
            ['ID', 'Slug', 'Status'],
            [[$tenant->id, $tenant->slug, $tenant->status]]
        );

        return self::SUCCESS;
    }

    protected function addOwner(Tenant $tenant, string $email): void
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => ucfirst(Str::before($email, '@')),
                'password' => Str::password(),
                'is_active' => true,
            ]
        );

        $membership = Membership::firstOrCreate(
            ['user_id' => $user->id, 'tenant_id' => $tenant->id],
            ['status' => Membership::STATUS_ACTIVE, 'joined_at' => now()]
        );

        $role = Role::where('tenant_id', $tenant->id)->where('slug', 'owner')->first();

        if ($role === null) {
            $role = Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'Owner',
                'slug' => 'owner',
                'is_system' => true,
            ]);
            $role->permissions()->sync(Permission::pluck('id')->all());
        }

        $membership->roles()->syncWithoutDetaching($role->id);

        $this->info("User {$email} added to tenant '{$tenant->name}' as owner.");
    }
}
