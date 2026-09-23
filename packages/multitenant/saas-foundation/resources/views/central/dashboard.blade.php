@extends('layouts.app')
@php $pageTitle = 'Admin Dashboard'; @endphp

@section('content')
<div class="page-header">
    <h1>Admin Dashboard</h1>
    <div class="text-muted text-sm">The shop at a glance &middot; {{ $customerCount }} customers &middot; {{ $stats['totalUsers'] }} users</div>
</div>

{{-- Flash cards --}}
<div class="flash-grid">
    <div class="flashcard blue">
        <div class="flash-icon">&#9632;</div>
        <div class="flash-label">Customers</div>
        <div class="flash-value">{{ $customerCount }}</div>
        <div class="flash-sub">Across the whole platform</div>
    </div>
    <div class="flashcard gold">
        <div class="flash-icon">&#10003;</div>
        <div class="flash-label">Active</div>
        <div class="flash-value">{{ $stats['activeTenants'] }}</div>
        <div class="flash-sub">{{ number_format($stats['activeSubscriptions']) }} active subscriptions</div>
    </div>
    <div class="flashcard navy">
        <div class="flash-icon">&#9733;</div>
        <div class="flash-label">On Trial</div>
        <div class="flash-value">{{ $stats['trialTenants'] }}</div>
        <div class="flash-sub">Winning them over</div>
    </div>
    <div class="flashcard black">
        <div class="flash-icon">&#9888;</div>
        <div class="flash-label">Suspended</div>
        <div class="flash-value">{{ $stats['suspendedTenants'] }}</div>
        <div class="flash-sub">{{ $stats['totalUsers'] }} people onboard</div>
    </div>
</div>

{{-- Milestone / Hurray --}}
@if ($customerCount >= $milestone)
<div class="hurray">
    <div class="hurray-emoji">&#127881; &#127947;&#65039; &#127881;</div>
    <div class="hurray-title">Hurray! You hit the {{ $milestone }}-customer milestone! &#127881;</div>
    <div class="hurray-text">{{ $customerCount }} customers and growing &mdash; keep up the great work selling the shop!</div>
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
        <strong>{{ $milestone - $customerCount }} to go!</strong> Reach {{ $milestone }} customers and we&rsquo;ll throw confetti.
    </div>
</div>
@endif

{{-- Charts --}}
<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3>Customer mix</h3></div>
        <div class="card-body">
            @php
                $conic = 'conic-gradient(#e5e7eb 0% 100%)';
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
                    'Trial' => 'gold',
                    'Suspended' => 'navy',
                    default => 'black',
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
                <p>No security events yet &#128077;</p>
            </div>
            @endforelse
        </div>
    </div>
</div>
@endsection