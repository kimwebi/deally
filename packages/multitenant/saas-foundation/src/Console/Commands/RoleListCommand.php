<?php

namespace SaasFoundation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;

class RoleListCommand extends Command
{
    protected $signature = 'role:list
                            {--tenant= : Only list roles for the given tenant UUID or slug}';

    protected $description = 'List roles and their permissions';

    public function handle(): int
    {
        $tenantIdentifier = $this->option('tenant');

        $query = Role::with('permissions');

        if ($tenantIdentifier !== null && $tenantIdentifier !== '') {
            $tenant = Str::isUuid($tenantIdentifier)
                ? Tenant::withTrashed()->find($tenantIdentifier)
                : Tenant::withTrashed()->where('slug', $tenantIdentifier)->first();

            if ($tenant === null) {
                $this->error('Tenant not found.');

                return self::FAILURE;
            }

            $query->where('tenant_id', $tenant->id);
        }

        $roles = $query->orderBy('name')->get();

        if ($roles->isEmpty()) {
            $this->info('No roles found.');

            return self::SUCCESS;
        }

        $rows = $roles->map(function (Role $role): array {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'tenant' => $role->tenant_id ?? 'system',
                'system' => $role->isSystem() ? 'yes' : 'no',
                'permissions' => (string) $role->permissions->count(),
            ];
        })->all();

        $this->table(
            ['ID', 'Name', 'Slug', 'Tenant', 'System', 'Permissions'],
            $rows
        );

        return self::SUCCESS;
    }
}
