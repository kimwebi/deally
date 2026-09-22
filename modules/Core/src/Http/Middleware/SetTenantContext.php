<?php

namespace Deally\Core\Http\Middleware;

use Closure;
use Deally\Core\Services\TenantConnectionBinder;
use Illuminate\Http\Request;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Tenancy\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function __construct(protected TenantConnectionBinder $binder) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $sessionKey = config('saas.auth.session_key', 'tenant_id');
            $sessionTenantId = session($sessionKey);

            $membership = filled($sessionTenantId)
                ? $user->memberships()
                    ->with('tenant')
                    ->where('tenant_id', $sessionTenantId)
                    ->first()
                : null;

            if ($membership === null || ! $membership->isActive()) {
                $membership = $user->memberships()
                    ->with('tenant')
                    ->active()
                    ->first();

                if (filled($sessionTenantId)) {
                    session()->forget($sessionKey);
                }
            }

            if ($membership === null && $user->isSuperAdmin()) {
                if (filled($sessionTenantId)) {
                    $tenant = Tenant::query()->whereKey($sessionTenantId)->first();

                    if ($tenant !== null) {
                        $this->binder->bind($tenant);

                        app(TenantContext::class)->initialize($tenant, $user);
                    }
                }

                if (! app(TenantContext::class)->check()) {
                    return redirect()->route('central.dashboard');
                }
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
