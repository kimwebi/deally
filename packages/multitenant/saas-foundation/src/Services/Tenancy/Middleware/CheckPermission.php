<?php

namespace SaasFoundation\Services\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use SaasFoundation\Services\Tenancy\TenantContext;

class CheckPermission
{
    public function __construct(
        protected TenantContext $context
    ) {}

    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        $user = $this->context->user();

        if ($user === null) {
            return response('Unauthenticated.', 401);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = $this->context->tenant();

        if ($tenant === null) {
            return response('No tenant context.', 403);
        }

        if (! $user->hasPermissionInTenant($permission, $tenant)) {
            return response('Unauthorized. Missing permission: '.$permission, 403);
        }

        return $next($request);
    }
}
