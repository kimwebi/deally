<?php

namespace SaasFoundation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SaasFoundation\Models\Tenant;

class TenantShowCommand extends Command
{
    protected $signature = 'tenant:show
                            {--tenant= : The tenant UUID}
                            {--slug= : The tenant slug}';

    protected $description = 'Show details for a tenant';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $slug = $this->option('slug');

        if (($tenantId === null && $slug === null) || ($tenantId !== null && $slug !== null)) {
            $this->error('Provide exactly one of --tenant or --slug.');

            return self::FAILURE;
        }

        $tenant = $this->resolveTenant($tenantId ?? $slug);

        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $tenant->id],
                ['Name', $tenant->name],
                ['Slug', $tenant->slug],
                ['Status', $tenant->trashed() ? 'deleted' : $tenant->status],
                ['Timezone', $tenant->timezone ?? '-'],
                ['Locale', $tenant->locale ?? '-'],
                ['Currency', $tenant->currency ?? '-'],
                ['Provisioning Status', $tenant->provisioning_status ?? '-'],
                ['Created At', $tenant->created_at?->toDateTimeString()],
                ['Deleted At', $tenant->deleted_at?->toDateTimeString()],
                ['Domains', (string) $tenant->domains()->count()],
                ['Members', (string) $tenant->memberships()->count()],
                ['Projects', (string) $tenant->projects()->count()],
                ['Subscriptions', (string) $tenant->subscriptions()->count()],
            ]
        );

        return self::SUCCESS;
    }

    protected function resolveTenant(string $identifier): ?Tenant
    {
        return Str::isUuid($identifier)
            ? Tenant::withTrashed()->find($identifier)
            : Tenant::withTrashed()->where('slug', $identifier)->first();
    }
}
