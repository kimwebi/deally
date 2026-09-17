<?php

namespace SaasFoundation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Tenancy\TenantContext;

class TenancyRunCommand extends Command
{
    protected $signature = 'tenancy:run
                            {--tenant= : Run for a single tenant by UUID or slug}
                            {--all : Run for all active tenants}
                            {--command= : Artisan command to execute per tenant (e.g. config:clear)}';

    protected $description = 'Run an artisan command or callback within each tenant context';

    public function handle(): int
    {
        $tenantIdentifier = $this->option('tenant');
        $all = (bool) $this->option('all');
        $command = $this->option('command');

        if (($tenantIdentifier !== null && $all) || ($tenantIdentifier === null && ! $all)) {
            $this->error('Provide exactly one of --tenant or --all.');

            return self::FAILURE;
        }

        if ($command === null || $command === '') {
            $this->error('The --command option specifying which artisan command to run per tenant is required.');

            return self::FAILURE;
        }

        $tenants = $all
            ? Tenant::active()->get()
            : [$this->resolveTenant($tenantIdentifier)];

        if ($tenants[0] === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $context = app(TenantContext::class);

        foreach ($tenants as $tenant) {
            $context->run($tenant, function () use ($command): void {
                $exitCode = Artisan::call($command);

                $output = trim(Artisan::output());

                $this->line($output);

                if ($exitCode !== 0) {
                    $this->error("Command '{$command}' failed with exit code {$exitCode}.");
                }
            });

            $this->info("Command '{$command}' executed for tenant '{$tenant->name}'.");
        }

        return self::SUCCESS;
    }

    protected function resolveTenant(string $identifier): ?Tenant
    {
        return Str::isUuid($identifier)
            ? Tenant::withTrashed()->find($identifier)
            : Tenant::withTrashed()->where('slug', $identifier)->first();
    }
}
