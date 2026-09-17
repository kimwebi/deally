<?php

namespace SaasFoundation\Services\Tenancy\Resolvers;

use Illuminate\Http\Request;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Tenancy\Contracts\TenantResolverInterface;

class HeaderResolver implements TenantResolverInterface
{
    public function resolve(Request $request): ?Tenant
    {
        $tenantId = $request->header('X-Tenant-ID');

        if (empty($tenantId)) {
            return null;
        }

        $tenant = Tenant::find($tenantId);

        if ($tenant === null) {
            return null;
        }

        if (! $tenant->isActive()) {
            return null;
        }

        return $tenant;
    }

    public function name(): string
    {
        return 'header';
    }
}
