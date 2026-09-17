<?php

namespace SaasFoundation\Http\Controllers;

use Illuminate\Http\Request;
use SaasFoundation\Models\Tenant;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isSuperAdmin()) {
            return redirect()->route('central.dashboard');
        }

        $tenant = Tenant::find(session('tenant_id'));

        if ($tenant && $user->belongsToTenant($tenant)) {
            return redirect()->route('tenant.dashboard', $tenant);
        }

        $membership = $user->memberships()
            ->with('tenant')
            ->active()
            ->first();

        if ($membership) {
            return redirect()->route('tenant.dashboard', $membership->tenant);
        }

        return redirect()->route('welcome');
    }
}
