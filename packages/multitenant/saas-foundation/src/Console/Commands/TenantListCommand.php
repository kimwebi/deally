<?php

namespace SaasFoundation\Console\Commands;

use Illuminate\Console\Command;
use SaasFoundation\Models\Tenant;

class TenantListCommand extends Command
{
    protected $signature = 'tenant:list
                            {--status= : Filter tenants by status (e.g. active, suspended, trial)}
                            {--format=table : Output format: table or json}';

    protected $description = 'List all tenants with their status';

    public function handle(): int
    {
        $status = $this->option('status');
        $format = $this->option('format');

        if (! in_array($format, ['table', 'json'], true)) {
            $this->error('Invalid format. Use --format=table or --format=json.');

            return self::FAILURE;
        }

        $query = Tenant::withTrashed();

        if ($status !== null) {
            $query->where('status', $status);
        }

        $tenants = $query->orderBy('created_at')->get();

        $rows = $tenants->map(function (Tenant $tenant): array {
            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->trashed() ? 'deleted' : $tenant->status,
                'deleted_at' => $tenant->deleted_at?->toDateTimeString(),
            ];
        })->all();

        if ($format === 'json') {
            $this->line(json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Slug', 'Status', 'Deleted At'],
            $rows
        );

        return self::SUCCESS;
    }
}
