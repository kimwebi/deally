<?php

namespace SaasFoundation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SaasFoundation\Models\Tenant;

class TenantDomainVerifyCommand extends Command
{
    protected $signature = 'tenant:domain:verify
                            {--tenant= : The tenant UUID or slug (required)}
                            {--domain= : The domain name (required)}';

    protected $description = 'Verify a tenant domain';

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
            ? Tenant::find($identifier)
            : Tenant::where('slug', $identifier)->first();

        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $domainModel = $tenant->domains()->where('domain', $domain)->first();

        if ($domainModel === null) {
            $this->error("Domain '{$domain}' not found on tenant '{$tenant->name}'.");

            return self::FAILURE;
        }

        $domainModel->update(['is_verified' => true, 'is_active' => true]);

        $this->info("Domain '{$domain}' verified for tenant '{$tenant->name}'.");

        return self::SUCCESS;
    }
}
