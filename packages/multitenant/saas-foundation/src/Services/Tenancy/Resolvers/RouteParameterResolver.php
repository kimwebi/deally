<?php

namespace SaasFoundation\Services\Tenancy\Resolvers;

use Illuminate\Http\Request;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Tenancy\Contracts\TenantResolverInterface;

class RouteParameterResolver implements TenantResolverInterface
{
    public function resolve(Request $request): ?Tenant
    {
        $tenantParam = $request->route('tenant');

        if (empty($tenantParam)) {
            return null;
        }

        $tenant = $tenantParam instanceof Tenant
            ? $tenantParam
            : Tenant::find($tenantParam);

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
        return 'route_parameter';
    }
}
