<?php

namespace SaasFoundation\Http\Controllers\Central;

use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Models\AuditLog;
use SaasFoundation\Models\Subscription;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;

class CentralDashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'totalTenants' => Tenant::withTrashed()->count(),
            'activeTenants' => Tenant::where('status', Tenant::STATUS_ACTIVE)->count(),
            'suspendedTenants' => Tenant::where('status', Tenant::STATUS_SUSPENDED)->count(),
            'trialTenants' => Tenant::where('status', Tenant::STATUS_TRIAL)->count(),
            'totalUsers' => User::count(),
            'activeSubscriptions' => Subscription::where('status', Subscription::STATUS_ACTIVE)->count(),
        ];

        $recentTenants = Tenant::withCount('memberships')
            ->latest()
            ->limit(5)
            ->get();

        $recentSecurityEvents = AuditLog::with(['user', 'tenant'])
            ->where('action', 'like', '%security%')
            ->orWhere(function ($query): void {
                $query->whereIn('action', ['login.failed', 'login.success', 'two_factor.failed', 'permission.denied']);
            })
            ->latest()
            ->limit(5)
            ->get();

        return view('central.dashboard', compact('stats', 'recentTenants', 'recentSecurityEvents'));
    }
}
