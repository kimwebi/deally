<?php

namespace SaasFoundation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SaasFoundation\Models\Domain;
use SaasFoundation\Models\Tenant;

class TenantDomainAddCommand extends Command
{
    protected $signature = 'tenant:domain:add
                            {--tenant= : The tenant UUID or slug (required)}
                            {--domain= : The domain name (required)}
                            {--type=subdomain : Domain type: subdomain or custom}';

    protected $description = 'Add a domain to a tenant';

    public function handle(): int
    {
        $identifier = $this->option('tenant');
        $domain = $this->option('domain');
        $type = $this->option('type');

        if ($identifier === null || $identifier === '') {
            $this->error('The --tenant option is required.');

            return self::FAILURE;
        }

        if ($domain === null || $domain === '') {
            $this->error('The --domain option is required.');

            return self::FAILURE;
        }

        if (! in_array($type, [Domain::TYPE_SUBDOMAIN, Domain::TYPE_CUSTOM], true)) {
            $this->error('Invalid type. Use --type=subdomain or --type=custom.');

            return self::FAILURE;
        }

        $tenant = Str::isUuid($identifier)
            ? Tenant::find($identifier)
            : Tenant::where('slug', $identifier)->first();

        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $existing = Domain::where('domain', $domain)->first();

        if ($existing !== null) {
            $this->error("Domain '{$domain}' already belongs to another tenant.");

            return self::FAILURE;
        }

        $domainModel = $tenant->domains()->firstOrCreate(
            ['domain' => $domain],
            [
                'type' => $type,
                'is_primary' => $tenant->domains()->count() === 0,
                'is_verified' => $type === Domain::TYPE_SUBDOMAIN,
                'is_active' => true,
            ]
        );

        $this->info("Domain '{$domain}' added to tenant '{$tenant->name}'.");
        $this->table(
            ['Domain', 'Type', 'Verified', 'Verification Token'],
            [[$domainModel->domain, $domainModel->type, $domainModel->is_verified ? 'yes' : 'no', $domainModel->verification_token]]
        );

        return self::SUCCESS;
    }
}
