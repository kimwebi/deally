<?php

namespace SaasFoundation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class TenancyStatusCommand extends Command
{
    protected $signature = 'tenancy:status';

    protected $description = 'Show the tenancy system status';

    public function handle(): int
    {
        $this->components->info('Tenancy System Status');

        $this->checkConnection();
        $this->checkCache();
        $this->checkQueue();

        $this->newLine();
        $this->components->twoColumnDetail('Mode', Config::get('saas.mode', Config::get('tenancy.driver', 'database_per_tenant')));
        $this->components->twoColumnDetail('Tenant Identifier', Config::get('tenancy.identifier.type', 'uuid'));
        $this->components->twoColumnDetail('DB Connections', (string) count(Config::get('database.connections', [])));

        $resolvers = Config::get('tenancy.resolvers', []);
        $enabled = collect($resolvers)
            ->filter(fn ($config) => ($config['enabled'] ?? false) === true)
            ->keys()
            ->implode(', ');

        $this->components->twoColumnDetail('Enabled Resolvers', $enabled ?: '-');

        return self::SUCCESS;
    }

    protected function checkConnection(): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('Database', '');

        try {
            DB::connection()->getPdo();
            $this->components->twoColumnDetail('  Default Connection', Config::get('database.default'));
            $this->components->twoColumnDetail('  Status', 'OK');
        } catch (\Throwable $exception) {
            $this->components->twoColumnDetail('  Default Connection', Config::get('database.default'));
            $this->components->twoColumnDetail('  Status', 'FAIL');
            $this->components->twoColumnDetail('  Error', $exception->getMessage());
        }
    }

    protected function checkCache(): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('Cache', '');

        $store = Config::get('cache.default', 'array');
        $probeKey = 'tenant:status:probe';
        $probe = 'ok-'.uniqid('', true);

        try {
            Cache::put($probeKey, $probe, 30);
            $result = Cache::get($probeKey) === $probe;

            $this->components->twoColumnDetail('  Store', $store);
            $this->components->twoColumnDetail('  Status', $result ? 'OK' : 'FAIL');

            if ($result) {
                Cache::forget($probeKey);
            }
        } catch (\Throwable $exception) {
            $this->components->twoColumnDetail('  Store', $store);
            $this->components->twoColumnDetail('  Status', 'FAIL');
            $this->components->twoColumnDetail('  Error', $exception->getMessage());
        }
    }

    protected function checkQueue(): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('Queue', '');

        $this->components->twoColumnDetail('  Connection', Config::get('queue.default', 'sync'));
        $this->components->twoColumnDetail('  Tenant Prefix', Config::get('tenancy.queue.prefix', 'tenant:%s:'));
    }
}
