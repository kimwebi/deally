<?php

namespace SaasFoundation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Tenant;

class SwitchTenantController extends Controller
{
    public function switch(Request $request, Tenant $tenant)
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && ! $user->belongsToTenant($tenant)) {
            abort(403, 'You are not a member of this tenant.');
        }

        $membership = Membership::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $user->isSuperAdmin() && $membership && $membership->status !== Membership::STATUS_ACTIVE) {
            abort(403, 'Your membership in this tenant is not active.');
        }

        session([config('saas.auth.session_key', 'tenant_id') => $tenant->id]);

        $redirectTo = config('saas.switch_redirect');

        if (filled($redirectTo) && Route::has($redirectTo)) {
            return redirect()
                ->route($redirectTo)
                ->with('success', "Now working in '{$tenant->name}'.");
        }

        return redirect()
            ->route('tenant.dashboard', $tenant)
            ->with('success', "Now working in '{$tenant->name}'.");
    }
}
