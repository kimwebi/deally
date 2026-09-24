<?php

namespace SaasFoundation\Http\Controllers\Central;

use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Models\AuditLog;
use SaasFoundation\Models\Subscription;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;

class CentralDashboardController extends Controller
{
    /**
     * The celebratory tenant milestone. Reaching this many customers shows
     * the milestone panel on the dashboard and the setup console.
     */
    public const MILESTONE_TARGET = 10;

    public function index()
    {
        $statuses = [
            'Active' => Tenant::where('status', Tenant::STATUS_ACTIVE)->count(),
            'Trial' => Tenant::where('status', Tenant::STATUS_TRIAL)->count(),
            'Suspended' => Tenant::where('status', Tenant::STATUS_SUSPENDED)->count(),
            'Other' => Tenant::whereNotIn('status', [
                Tenant::STATUS_ACTIVE,
                Tenant::STATUS_TRIAL,
                Tenant::STATUS_SUSPENDED,
            ])->count(),
        ];

        $customerCount = array_sum($statuses);

        $donut = [];
        foreach ($statuses as $label => $count) {
            $donut[] = [
                'label' => $label,
                'count' => $count,
                'pct' => $customerCount > 0 ? (int) round($count / $customerCount * 100) : 0,
                'color' => $this->statusColor($label),
            ];
        }

        $stats = [
            'totalTenants' => $customerCount,
            'activeTenants' => $statuses['Active'],
            'suspendedTenants' => $statuses['Suspended'],
            'trialTenants' => $statuses['Trial'],
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

        return view('central.dashboard', compact(
            'stats',
            'recentTenants',
            'recentSecurityEvents',
            'donut',
            'customerCount',
            'statuses',
        ))->with('milestone', self::MILESTONE_TARGET);
    }

    protected function statusColor(string $label): string
    {
        return match ($label) {
            'Active' => '#3b6fe0',
            'Trial' => '#8b94a3',
            'Suspended' => '#e5484d',
            default => '#4a5059',
        };
    }
}
