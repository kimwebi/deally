<?php

namespace SaasFoundation\Services\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use SaasFoundation\Services\Tenancy\TenantContext;

class PreventCrossTenantAccess
{
    public function __construct(
        protected TenantContext $context
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $currentTenantId = $this->context->id();

        if ($currentTenantId === null) {
            return $next($request);
        }

        $routeParameters = $request->route()?->parameters() ?? [];

        foreach ($routeParameters as $key => $value) {
            if (str_contains($key, 'tenant') && is_string($value) && $value !== $currentTenantId) {
                return response('Cross-tenant access denied.', 403);
            }
        }

        $queryTenant = $request->query('tenant_id');

        if ($queryTenant !== null && $queryTenant !== $currentTenantId) {
            return response('Cross-tenant access denied.', 403);
        }

        $headerTenant = $request->header('X-Tenant-ID');

        if ($headerTenant !== null && $headerTenant !== $currentTenantId) {
            return response('Cross-tenant access denied.', 403);
        }

        return $next($request);
    }
}
