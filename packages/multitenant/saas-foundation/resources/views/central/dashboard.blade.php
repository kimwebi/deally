@extends('layouts.app')
@php $pageTitle = 'Admin Dashboard'; @endphp

@section('content')
<div class="page-header">
    <h1>Admin Dashboard</h1>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-card-label">Total Tenants</div>
        <div class="stat-card-value">{{ number_format($stats['totalTenants']) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">Active</div>
        <div class="stat-card-value" style="color:var(--success);">{{ number_format($stats['activeTenants']) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">Suspended</div>
        <div class="stat-card-value" style="color:var(--warning);">{{ number_format($stats['suspendedTenants']) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">On Trial</div>
        <div class="stat-card-value" style="color:var(--primary);">{{ number_format($stats['trialTenants']) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">Total Users</div>
        <div class="stat-card-value">{{ number_format($stats['totalUsers']) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">Active Subscriptions</div>
        <div class="stat-card-value">{{ number_format($stats['activeSubscriptions']) }}</div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h3>Recent Tenants</h3>
            <a href="{{ route('central.tenants.index') }}" class="btn btn-secondary btn-sm">View all</a>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Members</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentTenants as $tenant)
                        <tr>
                            <td>
                                <a href="{{ route('central.tenants.show', $tenant) }}">{{ $tenant->name }}</a>
                            </td>
                            <td>
                                @php
                                $badgeClass = match($tenant->status) {
                                    'active' => 'badge-success',
                                    'trial' => 'badge-primary',
                                    'suspended' => 'badge-warning',
                                    'inactive', 'archived' => 'badge-gray',
                                    default => 'badge-gray',
                                };
                                @endphp
                                <span class="badge {{ $badgeClass }}">{{ ucfirst($tenant->status) }}</span>
                            </td>
                            <td>{{ $tenant->memberships_count }}</td>
                            <td class="text-muted text-sm">{{ $tenant->created_at->diffForHumans() }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted" style="padding:24px;">No tenants yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Security Events</h3>
            <a href="{{ route('central.audit.index') }}" class="btn btn-secondary btn-sm">View all</a>
        </div>
        <div class="card-body" style="padding:0;">
            @forelse($recentSecurityEvents as $event)
            <div class="list-group-item">
                <div>
                    <div style="font-size:14px; font-weight:500;">{{ $event->action }}</div>
                    <div class="text-muted text-sm">{{ $event->user?->name ?? 'System' }} &middot; {{ $event->created_at->diffForHumans() }}</div>
                </div>
                @if($event->tenant)
                <span class="badge badge-gray">{{ $event->tenant->name }}</span>
                @endif
            </div>
            @empty
            <div class="text-center text-muted" style="padding:24px;">No recent security events.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection