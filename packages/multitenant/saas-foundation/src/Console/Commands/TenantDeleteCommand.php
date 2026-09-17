<?php

namespace SaasFoundation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SaasFoundation\Models\Tenant;

class TenantDeleteCommand extends Command
{
    protected $signature = 'tenant:delete
                            {--tenant= : The tenant UUID or slug (required)}
                            {--force : Force delete the tenant permanently}';

    protected $description = 'Soft-delete a tenant (or force delete with --force)';

    public function handle(): int
    {
        $identifier = $this->option('tenant');

        if ($identifier === null || $identifier === '') {
            $this->error('The --tenant option is required.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');

        $tenant = $this->resolveTenant($identifier, $force);

        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        if ($force) {
            if (! $tenant->trashed()) {
                $tenant->delete();
            }

            $tenant->forceDelete();

            $this->info("Tenant '{$tenant->name}' permanently deleted.");

            return self::SUCCESS;
        }

        if ($tenant->trashed()) {
            $this->error('Tenant is already soft-deleted. Use --force to permanently delete.');

            return self::FAILURE;
        }

        $tenant->update(['status' => Tenant::STATUS_INACTIVE]);
        $tenant->delete();

        $this->info("Tenant '{$tenant->name}' soft-deleted.");

        return self::SUCCESS;
    }

    protected function resolveTenant(string $identifier, bool $includeTrashed): ?Tenant
    {
        return Str::isUuid($identifier)
            ? Tenant::when($includeTrashed, fn ($query) => $query->withTrashed())->find($identifier)
            : Tenant::when($includeTrashed, fn ($query) => $query->withTrashed())->where('slug', $identifier)->first();
    }
}
