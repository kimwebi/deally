<?php

namespace SaasFoundation\Console\Commands;

use Illuminate\Console\Command;
use SaasFoundation\Models\Permission;

class PermissionListCommand extends Command
{
    protected $signature = 'permission:list
                            {--group= : Only list permissions for the given group}';

    protected $description = 'List permissions grouped by category';

    public function handle(): int
    {
        $group = $this->option('group');

        $query = Permission::query();

        if ($group !== null && $group !== '') {
            $query->where('group_name', $group);
        }

        $permissions = $query->orderBy('group_name')->orderBy('name')->get();

        if ($permissions->isEmpty()) {
            $this->info($group !== null ? "No permissions found in group '{$group}'." : 'No permissions found.');

            return self::SUCCESS;
        }

        $rows = $permissions->map(fn (Permission $permission): array => [
            'name' => $permission->name,
            'slug' => $permission->slug,
            'group' => $permission->group_name,
            'description' => $permission->description ?? '-',
        ])->all();

        $this->table(
            ['Name', 'Slug', 'Group', 'Description'],
            $rows
        );

        return self::SUCCESS;
    }
}
