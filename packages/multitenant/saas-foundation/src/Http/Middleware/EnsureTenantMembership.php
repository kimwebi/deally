<?php

namespace SaasFoundation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SaasFoundation\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->route('tenant');

        if (! $tenant instanceof Tenant) {
            $tenant = is_string($tenant) || is_int($tenant) ? Tenant::find($tenant) : null;
        }

        if (! $tenant) {
            abort(404, 'Tenant not found.');
        }

        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->isSuperAdmin() && ! $user->belongsToTenant($tenant)) {
            abort(403, 'You are not a member of this tenant.');
        }

        session(['tenant_id' => $tenant->id]);

        return $next($request);
    }
}
