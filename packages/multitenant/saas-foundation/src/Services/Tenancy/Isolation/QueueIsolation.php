<?php

namespace SaasFoundation\Services\Tenancy\Isolation;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;
use SaasFoundation\Services\Tenancy\TenantContext;

class QueueIsolation
{
    public static function serializeTenantContext(): array
    {
        $context = app(TenantContext::class);

        if (! $context->check()) {
            return [];
        }

        return [
            'tenant_id' => $context->id(),
            'user_id' => $context->user()?->id,
        ];
    }

    public static function restoreTenantContext(array $payload): void
    {
        $context = app(TenantContext::class);

        if (isset($payload['tenant_id'])) {
            $tenant = Tenant::find($payload['tenant_id']);

            if ($tenant !== null && $tenant->isActive()) {
                $user = null;

                if (isset($payload['user_id'])) {
                    $user = User::find($payload['user_id']);
                }

                $context->initialize($tenant, $user);
            }
        }
    }

    public static function ensureTenantContext(callable $callback, array $tenantPayload): mixed
    {
        $context = app(TenantContext::class);

        $wasInitialized = $context->check();

        try {
            if (! $wasInitialized && isset($tenantPayload['tenant_id'])) {
                self::restoreTenantContext($tenantPayload);
            }

            return $callback();
        } finally {
            if (! $wasInitialized) {
                $context->end();
            }
        }
    }
}

trait EnsuresTenantContext
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?string $tenantId = null;

    public ?int $tenantUserId = null;

    public function __construct()
    {
        $context = app(TenantContext::class);

        if ($context->check()) {
            $this->tenantId = $context->id();
            $this->tenantUserId = $context->user()?->id;
        }
    }

    public function middleware(): array
    {
        return [];
    }

    protected function restoreContext(): void
    {
        QueueIsolation::restoreTenantContext([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->tenantUserId,
        ]);
    }

    protected function clearContext(): void
    {
        app(TenantContext::class)->end();
    }
}
