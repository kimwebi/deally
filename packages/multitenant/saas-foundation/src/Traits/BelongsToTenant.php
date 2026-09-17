<?php

namespace SaasFoundation\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Scopes\TenantScope;
use SaasFoundation\Services\Tenancy\TenantContext;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::creating(function (Model $model): void {
            if (in_array('tenant_id', $model->getFillable()) && $model->tenant_id === null) {
                $tenantId = app(TenantContext::class)->id();

                if ($tenantId !== null) {
                    $model->tenant_id = $tenantId;
                }
            }
        });

        static::addGlobalScope(new TenantScope);
    }

    public function scopeForTenant(Builder $query, ?string $tenantId = null): Builder
    {
        if ($tenantId === null) {
            $tenantId = app(TenantContext::class)->id();
        }

        if ($tenantId === null) {
            return $query;
        }

        return $query->where($this->getTable().'.tenant_id', $tenantId);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected static function validateTenantContext(): void
    {
        $context = app(TenantContext::class);

        if (! $context->check()) {
            throw new \RuntimeException('No tenant context initialized. All tenant-owned models require an active tenant context.');
        }
    }
}
