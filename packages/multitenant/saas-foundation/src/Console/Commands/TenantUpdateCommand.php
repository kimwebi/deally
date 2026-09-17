<?php

namespace SaasFoundation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SaasFoundation\Models\Tenant;

class TenantUpdateCommand extends Command
{
    protected $signature = 'tenant:update
                            {--tenant= : The tenant UUID or slug (required)}
                            {--name= : The new tenant name}
                            {--status= : The new tenant status}';

    protected $description = 'Update a tenant';

    public function handle(): int
    {
        $identifier = $this->option('tenant');

        if ($identifier === null || $identifier === '') {
            $this->error('The --tenant option is required.');

            return self::FAILURE;
        }

        $name = $this->option('name');
        $status = $this->option('status');

        if ($name === null && $status === null) {
            $this->error('Provide at least one of --name or --status.');

            return self::FAILURE;
        }

        $tenant = $this->resolveTenant($identifier);

        if ($tenant === null) {
            $this->error('Tenant not found.');

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

        if ($status !== null && ! in_array($status, $allowedStatuses, true)) {
            $this->error("Invalid status '{$status}'. Allowed: ".implode(', ', $allowedStatuses));

            return self::FAILURE;
        }

        $data = [];

        if ($name !== null) {
            $data['name'] = $name;
            $data['slug'] = Str::slug($name);
        }

        if ($status !== null) {
            $data['status'] = $status;
        }

        $tenant->update($data);

        $this->info("Tenant '{$tenant->name}' updated.");
        $this->table(
            ['ID', 'Slug', 'Status'],
            [[$tenant->id, $tenant->slug, $tenant->status]]
        );

        return self::SUCCESS;
    }

    protected function resolveTenant(string $identifier): ?Tenant
    {
        return Str::isUuid($identifier)
            ? Tenant::find($identifier)
            : Tenant::where('slug', $identifier)->first();
    }
}
