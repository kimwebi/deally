@extends('layouts.app')
@php $pageTitle = $tenant->name . ' Dashboard'; @endphp

@section('content')
<div class="page-header">
    <h1>{{ $tenant->name }}</h1>
    <span>
        @if($subscription)
        <span class="badge badge-success">Plan: {{ $subscription->plan->name }}</span>
        @if($subscription->status === 'trialing')
        <span class="badge badge-warning">Trial ends {{ $subscription->trial_ends_at?->format('M j, Y') }}</span>
        @endif
        @else
        <span class="badge badge-gray">No subscription</span>
        @endif
    </span>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-card-label">Members</div>
        <div class="stat-card-value">{{ number_format($stats['members']) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">Projects</div>
        <div class="stat-card-value">{{ number_format($stats['projects']) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">Active Projects</div>
        <div class="stat-card-value" style="color:var(--success);">{{ number_format($stats['activeProjects']) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">Audit Events</div>
        <div class="stat-card-value">{{ number_format($stats['auditEvents']) }}</div>
    </div>
</div>

<div class="grid-3">
    <div class="card">
        <div class="card-header">
            <h3>Recent Projects</h3>
            <a href="{{ route('tenant.projects.index', $tenant) }}" class="btn btn-secondary btn-sm">View all</a>
        </div>
        <div class="card-body" style="padding:0;">
            @forelse($recentProjects as $project)
            <div class="list-group-item">
                <div>
                    <div style="font-weight:500;">{{ $project->name }}</div>
                    <div class="text-muted text-sm">{{ $project->created_at->diffForHumans() }}</div>
                </div>
                <span class="badge {{ $project->status === 'active' ? 'badge-success' : 'badge-gray' }}">{{ ucfirst($project->status) }}</span>
            </div>
            @empty
            <div class="text-center text-muted" style="padding:24px;">No projects yet.</div>
            @endforelse
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Members</h3>
            <a href="{{ route('tenant.users.index', $tenant) }}" class="btn btn-secondary btn-sm">View all</a>
        </div>
        <div class="card-body" style="padding:0;">
            @forelse($recentMembers as $membership)
            <div class="list-group-item">
                <div class="flex items-center gap-2">
                    <div class="avatar" style="width:28px; height:28px; font-size:12px;">{{ substr($membership->user->name, 0, 1) }}</div>
                    <div>
                        <div style="font-size:14px; font-weight:500;">{{ $membership->user->name }}</div>
                        <div class="text-muted text-sm">{{ $membership->user->email }}</div>
                    </div>
                </div>
            </div>
            @empty
            <div class="text-center text-muted" style="padding:24px;">No members.</div>
            @endforelse
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Recent Activity</h3>
            <a href="{{ route('tenant.audit.index', $tenant) }}" class="btn btn-secondary btn-sm">View all</a>
        </div>
        <div class="card-body" style="padding:0;">
            @forelse($recentActivity as $log)
            <div class="list-group-item">
                <div>
                    <div style="font-size:14px;">{{ $log->action }}</div>
                    <div class="text-muted text-sm">{{ $log->user?->name ?? 'System' }} &middot; {{ $log->created_at->diffForHumans() }}</div>
                </div>
            </div>
            @empty
            <div class="text-center text-muted" style="padding:24px;">No recent activity.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection