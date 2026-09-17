<?php

namespace Deally\Core\Http\Middleware;

use Closure;
use Deally\Core\Services\TenantConnectionBinder;
use Illuminate\Http\Request;
use SaasFoundation\Services\Tenancy\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function __construct(protected TenantConnectionBinder $binder) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $membership = $user->memberships()
                ->with('tenant')
                ->active()
                ->first();

            if ($membership === null && $user->isSuperAdmin()) {
                return redirect()->route('central.dashboard');
            }

            if ($membership !== null) {
                $this->binder->bind($membership->tenant);

                app(TenantContext::class)->initialize($membership->tenant, $user);

                $user->setRelation('currentMembership', $membership);
            }
        }

        return $next($request);
    }
}
