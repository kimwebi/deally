@extends('layouts.app')
@php $pageTitle = $user->name; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('central.users.index') }}">Users</a>
    <span>/</span>
    <span>{{ $user->name }}</span>
</div>

<div class="page-header">
    <h1>{{ $user->name }}</h1>
    <form method="POST" action="{{ route('central.users.destroy', $user) }}" onsubmit="return confirm('Are you sure you want to delete this user?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger btn-sm">Delete User</button>
    </form>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3>Profile</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('central.users.update', $user) }}">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label class="form-label" for="name">Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="is_active" value="1" {{ $user->is_active ? 'checked' : '' }}>
                        Account active
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">Save changes</button>
            </form>
        </div>
    </div>

    <div>
        <div class="card mb-4">
            <div class="card-header"><h3>Memberships</h3></div>
            <div class="card-body" style="padding:0;">
                @forelse($memberships as $membership)
                <div class="list-group-item">
                    <div>
                        <div style="font-weight:500;">
                            <a href="{{ route('central.tenants.show', $membership->tenant) }}">{{ $membership->tenant->name }}</a>
                        </div>
                        <div class="text-muted text-sm">Joined {{ $membership->joined_at?->diffForHumans() ?? 'N/A' }}</div>
                    </div>
                    <div>
                        @foreach($membership->roles as $role)
                        <span class="badge badge-gray">{{ $role->name }}</span>
                        @endforeach
                    </div>
                </div>
                @empty
                <div class="text-center text-muted" style="padding:24px;">No memberships.</div>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3>Stats</h3></div>
            <div class="card-body">
                <div class="stat-grid" style="grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:0;">
                    <div class="stat-card">
                        <div class="stat-card-label">Memberships</div>
                        <div class="stat-card-value" style="font-size:20px;">{{ $user->memberships_count }}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-label">Audit Logs</div>
                        <div class="stat-card-value" style="font-size:20px;">{{ $user->audit_logs_count }}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-label">Activities</div>
                        <div class="stat-card-value" style="font-size:20px;">{{ $user->activities_count }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($recentActivity->isNotEmpty())
<div class="card" style="margin-top:24px;">
    <div class="card-header"><h3>Recent Activity</h3></div>
    <div class="card-body" style="padding:0;">
        @foreach($recentActivity as $log)
        <div class="list-group-item">
            <div>
                <div style="font-size:14px;">{{ $log->action }}</div>
                <div class="text-muted text-sm">
                    {{ $log->tenant?->name ?? 'Central' }} &middot; {{ $log->created_at->diffForHumans() }} &middot; {{ $log->ip_address }}
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif
@endsection