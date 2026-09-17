<?php

namespace SaasFoundation\Http\Controllers\Tenant;

use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Models\AuditLog;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Project;
use SaasFoundation\Models\Tenant;

class TenantDashboardController extends Controller
{
    public function index(Tenant $tenant)
    {
        $stats = [
            'members' => Membership::forTenant($tenant->id)->count(),
            'projects' => Project::forTenant($tenant->id)->count(),
            'activeProjects' => Project::forTenant($tenant->id)->active()->count(),
            'auditEvents' => AuditLog::forTenant($tenant->id)->count(),
        ];

        $recentProjects = Project::forTenant($tenant->id)
            ->latest()
            ->limit(5)
            ->get();

        $recentMembers = Membership::forTenant($tenant->id)
            ->with('user')
            ->latest()
            ->limit(5)
            ->get();

        $recentActivity = AuditLog::forTenant($tenant->id)
            ->with('user')
            ->latest()
            ->limit(5)
            ->get();

        $subscription = $tenant->subscriptions()
            ->with('plan', 'items.feature')
            ->latest()
            ->first();

        return view('tenant.dashboard', compact(
            'tenant',
            'stats',
            'recentProjects',
            'recentMembers',
            'recentActivity',
            'subscription'
        ));
    }
}
