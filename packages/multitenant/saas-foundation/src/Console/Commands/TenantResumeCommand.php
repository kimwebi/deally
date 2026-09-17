<?php

namespace SaasFoundation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SaasFoundation\Models\Tenant;

class TenantResumeCommand extends Command
{
    protected $signature = 'tenant:resume
                            {--tenant= : The tenant UUID or slug (required)}';

    protected $description = 'Resume a suspended tenant';

    public function handle(): int
    {
        $identifier = $this->option('tenant');

        if ($identifier === null || $identifier === '') {
            $this->error('The --tenant option is required.');

            return self::FAILURE;
        }

        $tenant = Str::isUuid($identifier)
            ? Tenant::find($identifier)
            : Tenant::where('slug', $identifier)->first();

        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        if (! $tenant->isSuspended()) {
            $this->info("Tenant '{$tenant->name}' is not suspended.");

            return self::SUCCESS;
        }

        $tenant->update([
            'status' => Tenant::STATUS_ACTIVE,
            'suspended_at' => null,
        ]);

        $this->info("Tenant '{$tenant->name}' resumed.");

        return self::SUCCESS;
    }
}
