<?php

namespace SaasFoundation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Tenant;

class TenantUserRemoveCommand extends Command
{
    protected $signature = 'tenant:user:remove
                            {--tenant= : The tenant UUID or slug (required)}
                            {--email= : The user email (required)}';

    protected $description = 'Remove a user from a tenant';

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
            ? Tenant::withTrashed()->find($identifier)
            : Tenant::withTrashed()->where('slug', $identifier)->first();

        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $membership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->whereHas('user', fn ($query) => $query->where('email', $email))
            ->first();

        if ($membership === null) {
            $this->error("User {$email} is not a member of tenant '{$tenant->name}'.");

            return self::FAILURE;
        }

        $membership->roles()->detach();
        $membership->delete();

        $this->info("User {$email} removed from tenant '{$tenant->name}'.");

        return self::SUCCESS;
    }
}
