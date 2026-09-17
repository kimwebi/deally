<?php

namespace SaasFoundation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;

class TenantUserAddCommand extends Command
{
    protected $signature = 'tenant:user:add
                            {--tenant= : The tenant UUID or slug (required)}
                            {--email= : The user email (required)}
                            {--role= : The role slug or name to assign}';

    protected $description = 'Add a user to a tenant';

    public function handle(): int
    {
        $identifier = $this->option('tenant');
        $email = $this->option('email');

        if ($identifier === null || $identifier === '') {
            $this->error('The --tenant option is required.');

            return self::FAILURE;
        }

        if ($email === null || $email === '') {
            $this->error('The --email option is required.');

            return self::FAILURE;
        }

        $tenant = Str::isUuid($identifier)
            ? Tenant::find($identifier)
            : Tenant::where('slug', $identifier)->first();

        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

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

        $role = $this->resolveRole($tenant);

        if ($role !== null) {
            $membership->roles()->syncWithoutDetaching($role->id);
        }

        $this->info("User {$email} added to tenant '{$tenant->name}'.");

        if ($role !== null) {
            $this->line("Assigned role: {$role->slug}");
        } else {
            $this->warn('No matching role found; user added without a role.');
        }

        return self::SUCCESS;
    }

    protected function resolveRole(Tenant $tenant): ?Role
    {
        $roleOption = $this->option('role');

        if ($roleOption !== null && $roleOption !== '') {
            $role = Role::where('tenant_id', $tenant->id)
                ->where(fn ($query) => $query->where('slug', $roleOption)->orWhere('name', $roleOption))
                ->first();

            if ($role !== null) {
                return $role;
            }

            $role = Role::whereNull('tenant_id')->where('slug', $roleOption)->first();

            if ($role !== null) {
                return $role;
            }

            $this->warn("Role '{$roleOption}' not found; falling back to the default member role.");
        }

        return Role::where('tenant_id', $tenant->id)->where('slug', 'member')->first()
            ?? Role::whereNull('tenant_id')->where('slug', 'member')->first();
    }
}
