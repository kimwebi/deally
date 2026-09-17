@extends('layouts.app')
@php $pageTitle = $tenant->name; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('central.tenants.index') }}">Tenants</a>
    <span>/</span>
    <span>{{ $tenant->name }}</span>
</div>

<div class="page-header">
    <h1>{{ $tenant->name }}</h1>
    <div class="flex gap-2">
        <form method="POST" action="{{ route('central.tenants.clone', $tenant) }}">
            @csrf
            <button type="submit" class="btn btn-info btn-sm">Clone to Troubleshoot</button>
        </form>
        @if($tenant->status === 'active')
        <form method="POST" action="{{ route('central.tenants.suspend', $tenant) }}">
            @csrf
            <button type="submit" class="btn btn-warning btn-sm">Suspend</button>
        </form>
        @elseif($tenant->status === 'suspended')
        <form method="POST" action="{{ route('central.tenants.restore', $tenant) }}">
            @csrf
            <button type="submit" class="btn btn-success btn-sm">Restore</button>
        </form>
        @endif
        <a href="{{ route('central.tenants.edit', $tenant) }}" class="btn btn-secondary btn-sm">Edit</a>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-card-label">Status</div>
        <div class="stat-card-value">
            @php
            $badgeClass = match($tenant->status) {
                'active' => 'badge-success',
                'trial' => 'badge-primary',
                'suspended' => 'badge-warning',
                default => 'badge-gray',
            };
            @endphp
            <span class="badge {{ $badgeClass }}">{{ ucfirst($tenant->status) }}</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">Members</div>
        <div class="stat-card-value">{{ $tenant->memberships_count }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">Projects</div>
        <div class="stat-card-value">{{ $tenant->projects_count }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">Roles</div>
        <div class="stat-card-value">{{ $tenant->roles_count }}</div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3>Details</h3></div>
        <div class="card-body">
            <div class="list-group">
                <div class="list-group-item">
                    <span class="text-muted">Slug</span>
                    <span class="mono">{{ $tenant->slug }}</span>
                </div>
                <div class="list-group-item">
                    <span class="text-muted">Timezone</span>
                    <span>{{ $tenant->timezone ?? 'UTC' }}</span>
                </div>
                <div class="list-group-item">
                    <span class="text-muted">Currency</span>
                    <span>{{ $tenant->currency ?? 'USD' }}</span>
                </div>
                <div class="list-group-item">
                    <span class="text-muted">Created</span>
                    <span>{{ $tenant->created_at->diffForHumans() }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Subscription</h3></div>
        <div class="card-body">
            @if($subscription)
            <div class="list-group">
                <div class="list-group-item">
                    <span class="text-muted">Plan</span>
                    <span>{{ $subscription->plan->name }}</span>
                </div>
                <div class="list-group-item">
                    <span class="text-muted">Status</span>
                    <span class="badge {{ $subscription->status === 'active' ? 'badge-success' : 'badge-gray' }}">{{ ucfirst($subscription->status) }}</span>
                </div>
                <div class="list-group-item">
                    <span class="text-muted">Started</span>
                    <span>{{ $subscription->starts_at?->diffForHumans() ?? 'N/A' }}</span>
                </div>
            </div>
            @else
            <div class="text-center text-muted" style="padding:24px;">No active subscription.</div>
            @endif
        </div>
    </div>
</div>

<div class="card" style="margin-top:24px;">
    <div class="card-header">
        <h3>Members</h3>
        <a href="{{ route('tenant.users.index', $tenant) }}" class="btn btn-secondary btn-sm">View all</a>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($members as $membership)
                    <tr>
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="avatar" style="width:28px; height:28px; font-size:12px;">{{ substr($membership->user->name, 0, 1) }}</div>
                                <div>
                                    <div style="font-weight:500;">{{ $membership->user->name }}</div>
                                    <div class="text-muted text-sm">{{ $membership->user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @foreach($membership->roles as $role)
                            <span class="badge badge-gray">{{ $role->name }}</span>
                            @endforeach
                        </td>
                        <td>
                            <span class="badge {{ $membership->status === 'active' ? 'badge-success' : 'badge-warning' }}">{{ ucfirst($membership->status) }}</span>
                        </td>
                        <td class="text-muted text-sm">{{ $membership->joined_at?->diffForHumans() ?? 'N/A' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center text-muted" style="padding:24px;">No members.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection