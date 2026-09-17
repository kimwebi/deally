@extends('layouts.app')
@php $pageTitle = 'Users'; @endphp

@section('content')
<div class="page-header">
    <h1>Users</h1>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('central.users.index') }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:end;">
            <div class="form-group" style="margin-bottom:0; flex:1; min-width:200px;">
                <label class="form-label">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name or email...">
            </div>
            <label class="form-check" style="margin-bottom:10px;">
                <input type="checkbox" name="super_admin" value="1" {{ request()->boolean('super_admin') ? 'checked' : '' }}>
                Super admins
            </label>
            <label class="form-check" style="margin-bottom:10px;">
                <input type="checkbox" name="inactive" value="1" {{ request()->boolean('inactive') ? 'checked' : '' }}>
                Inactive only
            </label>
            <button type="submit" class="btn btn-secondary">Filter</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Tenants</th>
                        <th>Role</th>
                        <th>Last login</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    <tr>
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="avatar">{{ substr($user->name, 0, 1) }}</div>
                                <div>
                                    <a href="{{ route('central.users.show', $user) }}" style="font-weight:500; color:#111827;">{{ $user->name }}</a>
                                    <div class="text-muted text-sm">{{ $user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $user->memberships_count }}</td>
                        <td>
                            @if($user->is_super_admin)
                            <span class="badge badge-warning">Super Admin</span>
                            @else
                            <span class="badge badge-gray">Member</span>
                            @endif
                            @if(!$user->is_active)
                            <span class="badge badge-danger" style="margin-left:4px;">Inactive</span>
                            @endif
                        </td>
                        <td class="text-muted text-sm">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                        <td class="text-muted text-sm">{{ $user->created_at->diffForHumans() }}</td>
                        <td class="text-right">
                            <div class="flex gap-2" style="justify-content:flex-end;">
                                <a href="{{ route('central.users.show', $user) }}" class="btn btn-secondary btn-sm">View</a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted" style="padding:32px;">No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($users->hasPages())
    <div class="card-footer">{{ $users->links() }}</div>
    @endif
</div>
@endsection