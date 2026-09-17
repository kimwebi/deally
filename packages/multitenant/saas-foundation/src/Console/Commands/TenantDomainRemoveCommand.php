<?php

namespace SaasFoundation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SaasFoundation\Models\Tenant;

class TenantDomainRemoveCommand extends Command
{
    protected $signature = 'tenant:domain:remove
                            {--tenant= : The tenant UUID or slug (required)}
                            {--domain= : The domain name (required)}';

    protected $description = 'Remove a domain from a tenant';

    public function handle(): int
    {
        $identifier = $this->option('tenant');
        $domain = $this->option('domain');

        if ($identifier === null || $identifier === '') {
            $this->error('The --tenant option is required.');

            return self::FAILURE;
        }

        if ($domain === null || $domain === '') {
            $this->error('The --domain option is required.');

            return self::FAILURE;
        }

        $tenant = Str::isUuid($identifier)
            ? Tenant::withTrashed()->find($identifier)
            : Tenant::withTrashed()->where('slug', $identifier)->first();

        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $deleted = $tenant->domains()->where('domain', $domain)->delete();

        if (! $deleted) {
            $this->error("Domain '{$domain}' not found on tenant '{$tenant->name}'.");

            return self::FAILURE;
        }

        $this->info("Domain '{$domain}' removed from tenant '{$tenant->name}'.");

        return self::SUCCESS;
    }
}
