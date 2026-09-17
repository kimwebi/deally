@extends('layouts.app')
@php $pageTitle = 'Members'; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('tenant.dashboard', $tenant) }}">{{ $tenant->name }}</a>
    <span>/</span>
    <span>Members</span>
</div>

<div class="page-header">
    <h1>Members</h1>
    <div class="flex gap-2">
        <a href="{{ route('tenant.invitations.create', $tenant) }}" class="btn btn-primary">Invite Member</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('tenant.users.index', $tenant) }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:end;">
            <div class="form-group" style="margin-bottom:0; flex:1; min-width:200px;">
                <label class="form-label">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name or email...">
            </div>
            <div class="form-group" style="margin-bottom:0; min-width:160px;">
                <label class="form-label">Role</label>
                <select name="role_id" class="form-control">
                    <option value="">All roles</option>
                    @foreach($roles as $role)
                    <option value="{{ $role->id }}" {{ request('role_id') == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
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
                        <th>Roles</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($members as $membership)
                    <tr>
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="avatar">{{ substr($membership->user->name, 0, 1) }}</div>
                                <div>
                                    <a href="{{ route('tenant.users.show', [$tenant, $membership]) }}" style="font-weight:500; color:#111827;">{{ $membership->user->name }}</a>
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
                        <td class="text-right">
                            <a href="{{ route('tenant.users.show', [$tenant, $membership]) }}" class="btn btn-secondary btn-sm">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted" style="padding:32px;">No members found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($members->hasPages())
    <div class="card-footer">{{ $members->links() }}</div>
    @endif
</div>
@endsection