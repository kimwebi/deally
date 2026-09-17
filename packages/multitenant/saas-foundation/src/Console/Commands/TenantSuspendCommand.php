<?php

namespace SaasFoundation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SaasFoundation\Models\Tenant;

class TenantSuspendCommand extends Command
{
    protected $signature = 'tenant:suspend
                            {--tenant= : The tenant UUID or slug (required)}
                            {--reason= : Optional reason for the suspension}';

    protected $description = 'Suspend a tenant';

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

        if ($tenant->trashed()) {
            $this->error('Tenant is deleted and cannot be suspended.');

            return self::FAILURE;
        }

        $metadata = $tenant->metadata ?? [];

        if ($this->option('reason') !== null) {
            $metadata['suspension_reason'] = $this->option('reason');
        }

        $tenant->update([
            'status' => Tenant::STATUS_SUSPENDED,
            'suspended_at' => now(),
            'metadata' => $metadata,
        ]);

        $this->info("Tenant '{$tenant->name}' suspended.");

        if ($this->option('reason') !== null) {
            $this->line("Reason: {$this->option('reason')}");
        }

        return self::SUCCESS;
    }
}
