<?php

namespace SaasFoundation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SaasFoundation\Models\Tenant;

class TenantRestoreCommand extends Command
{
    protected $signature = 'tenant:restore
                            {--tenant= : The tenant UUID or slug (required)}';

    protected $description = 'Restore a soft-deleted tenant';

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

        if (! $tenant->trashed()) {
            $this->info("Tenant '{$tenant->name}' is not deleted.");

            return self::SUCCESS;
        }

        $tenant->update(['status' => Tenant::STATUS_ACTIVE]);
        $tenant->restore();

        $this->info("Tenant '{$tenant->name}' restored.");

        return self::SUCCESS;
    }
}
