<?php

namespace SaasFoundation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SaasFoundation\Models\Tenant;

class TenantUserListCommand extends Command
{
    protected $signature = 'tenant:user:list
                            {--tenant= : The tenant UUID or slug (required)}';

    protected $description = 'List users in a tenant';

    public function handle(): int
    {
        $identifier = $this->option('tenant');

        if ($identifier === null || $identifier === '') {
            $this->error('The --tenant option is required.');

            return self::FAILURE;
        }

        $tenant = Str::isUuid($identifier)
            ? Tenant::withTrashed()->find($identifier)
            : Tenant::withTrashed()->where('slug', $identifier)->first();

        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $memberships = $tenant->memberships()
            ->with(['user', 'roles'])
            ->orderByDesc('joined_at')
            ->get();

        if ($memberships->isEmpty()) {
            $this->info("Tenant '{$tenant->name}' has no members.");

            return self::SUCCESS;
        }

        $rows = $memberships->map(function ($membership): array {
            return [
                'user_id' => $membership->user_id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'roles' => $membership->roles->pluck('slug')->implode(', '),
                'status' => $membership->status,
            ];
        })->all();

        $this->table(
            ['User ID', 'Name', 'Email', 'Roles', 'Status'],
            $rows
        );

        return self::SUCCESS;
    }
}
