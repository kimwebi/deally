@extends('layouts.app')
@php $pageTitle = 'Role - ' . $role->name; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('tenant.dashboard', $tenant) }}">{{ $tenant->name }}</a>
    <span>/</span>
    <a href="{{ route('tenant.roles.index', $tenant) }}">Roles</a>
    <span>/</span>
    <span>{{ $role->name }}</span>
</div>

<div class="page-header">
    <h1>{{ $role->name }}</h1>
    <div class="flex gap-2">
        @if(!$role->is_system)
        <a href="{{ route('tenant.roles.edit', [$tenant, $role]) }}" class="btn btn-primary btn-sm">Edit</a>
        @endif
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3>Details</h3></div>
        <div class="card-body">
            <div class="list-group">
                <div class="list-group-item">
                    <span class="text-muted">Description</span>
                    <span>{{ $role->description ?: '—' }}</span>
                </div>
                <div class="list-group-item">
                    <span class="text-muted">Type</span>
                    <span class="badge {{ $role->is_system ? 'badge-primary' : 'badge-gray' }}">{{ $role->is_system ? 'System' : 'Custom' }}</span>
                </div>
                <div class="list-group-item">
                    <span class="text-muted">Created</span>
                    <span>{{ $role->created_at->diffForHumans() }}</span>
                </div>
            </div>

            <h3 class="mt-4 mb-2">Permissions ({{ $role->permissions->count() }})</h3>
            @forelse($role->permissions->groupBy('group_name') as $group => $groupPermissions)
            <div style="margin-bottom:12px;">
                <div class="text-muted text-sm" style="text-transform:capitalize; font-weight:600;">{{ $group }}</div>
                <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:6px;">
                    @foreach($groupPermissions as $permission)
                    <span class="badge badge-gray">{{ $permission->name }}</span>
                    @endforeach
                </div>
            </div>
            @empty
            <div class="text-center text-muted" style="padding:16px;">No permissions assigned.</div>
            @endforelse

            @if(!$role->is_system)
            <form method="POST" action="{{ route('tenant.roles.destroy', [$tenant, $role]) }}" onsubmit="return confirm('Delete this role?');" class="mt-4">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">Delete Role</button>
            </form>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Assigned Members</h3></div>
        <div class="card-body" style="padding:0;">
            @forelse($role->memberships as $membership)
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
            <div class="text-center text-muted" style="padding:24px;">No members assigned.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection