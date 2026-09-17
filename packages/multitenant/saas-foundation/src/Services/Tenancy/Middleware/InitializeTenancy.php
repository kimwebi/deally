<?php

namespace SaasFoundation\Services\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Tenancy\TenantContext;
use SaasFoundation\Services\Tenancy\TenantResolver;

class InitializeTenancy
{
    public function __construct(
        protected TenantContext $context,
        protected TenantResolver $resolver
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $tenant = $this->resolver->resolve($request);

        if ($tenant === null) {
            return $this->handleMissingTenant($request);
        }

        if (! $tenant->isActive()) {
            return $this->handleInactiveTenant($request, $tenant);
        }

        $this->context->initialize($tenant, $request->user());

        if ($request->hasSession()) {
            $request->session()->put('tenant_id', $tenant->id);
        }

        try {
            return $next($request);
        } finally {
            $this->context->end();
        }
    }

    protected function handleMissingTenant(Request $request): mixed
    {
        return response('Tenant not found.', 404);
    }

    protected function handleInactiveTenant(Request $request, Tenant $tenant): mixed
    {
        return response('Tenant is not active.', 403);
    }
}
