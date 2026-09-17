<?php

namespace SaasFoundation\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Tenancy\TenantContext;

class ExportTenantData implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(
        protected Tenant $tenant,
        protected ?string $disk = null,
    ) {}

    public function handle(TenantContext $context): string
    {
        $context->initialize($this->tenant);

        try {
            $export = json_encode([
                'tenant' => $this->tenant->toArray(),
                'domains' => $this->tenant->domains()->get()->toArray(),
                'memberships' => $this->tenant->memberships()->with(['user', 'roles'])->get()->toArray(),
                'subscriptions' => $this->tenant->subscriptions()->with('plan', 'items.feature')->get()->toArray(),
                'roles' => $this->tenant->roles()->with('permissions')->get()->toArray(),
                'settings' => $this->tenant->settings()->get()->toArray(),
                'projects' => $this->tenant->projects()->get()->toArray(),
            ], JSON_PRETTY_PRINT);

            $disk = $this->disk ?? 'local';
            $path = "exports/{$this->tenant->id}-".now()->format('YmdHis').'.json';

            Storage::disk($disk)->put($path, $export);

            return $path;
        } finally {
            $context->end();
        }
    }

    public function middleware(): array
    {
        return [new WithoutOverlapping('tenant_'.$this->tenant->id)];
    }
}
