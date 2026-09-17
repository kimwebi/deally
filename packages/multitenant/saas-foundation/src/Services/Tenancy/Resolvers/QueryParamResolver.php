<?php

namespace SaasFoundation\Services\Tenancy\Resolvers;

use Illuminate\Http\Request;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Tenancy\Contracts\TenantResolverInterface;

class QueryParamResolver implements TenantResolverInterface
{
    public function resolve(Request $request): ?Tenant
    {
        $tenantId = $request->query('tenant');

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
        return 'query_param';
    }
}
