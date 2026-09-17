<?php

namespace SaasFoundation\Services\Tenancy\Resolvers;

use Illuminate\Http\Request;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Tenancy\Contracts\TenantResolverInterface;

class SessionResolver implements TenantResolverInterface
{
    public function resolve(Request $request): ?Tenant
    {
        $tenantId = $request->session()->get('tenant_id');

        if (empty($tenantId)) {
            return null;
        }

        $tenant = Tenant::find($tenantId);

        if ($tenant === null) {
            $request->session()->forget('tenant_id');

            return null;
        }

        if (! $tenant->isActive()) {
            $request->session()->forget('tenant_id');

            return null;
        }

        return $tenant;
    }

    public function name(): string
    {
        return 'session';
    }
}
