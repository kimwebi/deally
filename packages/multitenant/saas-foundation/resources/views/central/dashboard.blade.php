@extends('layouts.app')
@php $pageTitle = 'Admin Dashboard'; @endphp

@section('content')
<div class="page-header">
    <h1>Admin Dashboard</h1>
    <div class="text-muted text-sm">Platform at a glance &middot; {{ $customerCount }} customers &middot; {{ $stats['totalUsers'] }} users</div>
</div>

{{-- Stat cards --}}
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
        <div class="stat-label">Customers</div>
        <div class="stat-value">{{ $customerCount }}</div>
        <div class="stat-sub">Across the whole platform</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-check-circle-fill"></i></div>
        <div class="stat-label">Active</div>
        <div class="stat-value">{{ $stats['activeTenants'] }}</div>
        <div class="stat-sub">{{ number_format($stats['activeSubscriptions']) }} active subscriptions</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon grey"><i class="bi bi-hourglass-split"></i></div>
        <div class="stat-label">On Trial</div>
        <div class="stat-value">{{ $stats['trialTenants'] }}</div>
        <div class="stat-sub">Winning them over</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <div class="stat-label">Suspended</div>
        <div class="stat-value">{{ $stats['suspendedTenants'] }}</div>
        <div class="stat-sub">{{ $stats['totalUsers'] }} people onboard</div>
    </div>
</div>

{{-- Milestone / milestone reached --}}
@if ($customerCount >= $milestone)
<div class="hurray">
    <div class="hurray-emoji"><i class="bi bi-trophy-fill"></i></div>
    <div>
        <div class="hurray-title">Milestone reached &mdash; {{ $milestone }} customers</div>
        <div class="hurray-text">{{ $customerCount }} customers and growing. Keep up the great work.</div>
    </div>
</div>
@else
<div class="milestone">
    <div class="milestone-head">
        <div class="milestone-title"><span class="flag">&#127937;</span> Customer milestone</div>
        <div class="milestone-goal">{{ $customerCount }} / {{ $milestone }} customers</div>
    </div>
    <div class="milestone-track">
        <div class="milestone-fill" style="width: {{ min(100, (int) round($customerCount / $milestone * 100)) }}%;"></div>
    </div>
    <div class="milestone-note">
        <strong>{{ $milestone - $customerCount }} to go!</strong> Reach {{ $milestone }} customers to unlock the next milestone.
    </div>
</div>
@endif

{{-- Charts --}}
<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3>Customer mix</h3></div>
        <div class="card-body">
            @php
                $conic = 'conic-gradient(#2e343d 0% 100%)';
                if ($customerCount > 0) {
                    $parts = [];
                    $start = 0;
                    foreach ($donut as $i => $segment) {
                        $end = $i === array_key_last($donut) ? 100 : $start + $segment['pct'];
                        $parts[] = "{$segment['color']} {$start}% {$end}%";
                        $start = $end;
                    }
                    $conic = 'conic-gradient('.implode(', ', $parts).')';
                }
            @endphp
            <div class="chart-layout">
                <div class="donut-wrap">
                    <div class="donut" style="background: {{ $conic }};">
                        <div class="donut-hole">
                            <div class="donut-total">{{ $customerCount }}</div>
                            <div class="donut-caption">customers</div>
                        </div>
                    </div>
                </div>
                <div class="legend">
                    @foreach ($donut as $segment)
                    <div class="legend-row">
                        <span class="legend-dot" style="background: {{ $segment['color'] }};"></span>
                        <span>{{ $segment['label'] }}</span>
                        <span class="legend-count">{{ $segment['count'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Customers by status</h3></div>
        <div class="card-body">
            @php
                $max = max(1, max(array_values($statuses)));
            @endphp
            <div class="bar-chart">
                @foreach ($statuses as $label => $count)
                @php
                $barClass = match ($label) {
                    'Active' => 'blue',
                    'Trial' => 'grey',
                    'Suspended' => 'red',
                    default => 'dark',
                };
                @endphp
                <div class="bar-col">
                    <div class="bar-count">{{ $count }}</div>
                    <div class="bar-track">
                        <div class="bar-fill {{ $barClass }}" style="height: {{ (int) round($count / $max * 100) }}%;"></div>
                    </div>
                    <div class="bar-label">{{ $label }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="grid-2 mt-4">
    <div class="card">
        <div class="card-header">
            <h3>Recent Tenants</h3>
            <a href="{{ route('central.tenants.index') }}" class="btn btn-secondary btn-sm">View all</a>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Customer</th><th>Status</th><th>Members</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($recentTenants as $tenant)
                        <tr>
                            <td>{{ $tenant->name }}</td>
                            <td><span class="badge {{ in_array($tenant->status, ['active', 'trial']) ? 'badge-success' : 'badge-warning' }}">{{ ucfirst($tenant->status) }}</span></td>
                            <td>{{ $tenant->memberships_count }}</td>
                            <td><a href="{{ route('central.tenants.show', $tenant) }}" class="btn btn-secondary btn-sm">Open</a></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Recent Security Events</h3></div>
        <div class="card-body" style="padding:0;">
            @forelse ($recentSecurityEvents as $event)
            <div class="list-group-item">
                <div>{{ $event->action }}</div>
                <span class="text-muted text-sm">{{ $event->user?->name ?? 'System' }}</span>
            </div>
            @empty
            <div class="empty-state">
                <p>No security events yet <i class="bi bi-fingerprint"></i></p>
            </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
