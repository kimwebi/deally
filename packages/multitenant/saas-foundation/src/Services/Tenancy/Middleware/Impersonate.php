<?php

namespace SaasFoundation\Services\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use SaasFoundation\Models\User;
use SaasFoundation\Services\Tenancy\TenantContext;

class Impersonate
{
    public function __construct(
        protected TenantContext $context
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $admin = $request->user();

        if ($admin === null || ! $admin->isSuperAdmin()) {
            return response('Unauthorized. Super admin access required for impersonation.', 403);
        }

        $impersonateId = $request->header('X-Impersonate-User-ID');

        if (empty($impersonateId)) {
            return $next($request);
        }

        $targetUser = User::find($impersonateId);

        if ($targetUser === null) {
            return response('Target user not found.', 404);
        }

        $tenant = $this->context->tenant();

        if ($tenant === null) {
            return response('No tenant context for impersonation.', 403);
        }

        if (! $targetUser->belongsToTenant($tenant)) {
            return response('Target user does not belong to this tenant.', 403);
        }

        $this->context->setUser($targetUser);

        $request->headers->set('X-Impersonated-By', (string) $admin->id);
        $request->headers->set('X-Impersonated-User-ID', (string) $targetUser->id);

        return $next($request);
    }
}
