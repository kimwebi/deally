@extends('layouts.app')
@php $pageTitle = $membership->user->name; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('tenant.dashboard', $tenant) }}">{{ $tenant->name }}</a>
    <span>/</span>
    <a href="{{ route('tenant.users.index', $tenant) }}">Members</a>
    <span>/</span>
    <span>{{ $membership->user->name }}</span>
</div>

<div class="page-header">
    <h1>{{ $membership->user->name }}</h1>
    <form method="POST" action="{{ route('tenant.users.destroy', [$tenant, $membership]) }}" onsubmit="return confirm('Remove this member from the tenant?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger btn-sm">Remove from tenant</button>
    </form>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3>User Details</h3></div>
        <div class="card-body">
            <div class="list-group">
                <div class="list-group-item">
                    <span class="text-muted">Name</span>
                    <span>{{ $membership->user->name }}</span>
                </div>
                <div class="list-group-item">
                    <span class="text-muted">Email</span>
                    <span>{{ $membership->user->email }}</span>
                </div>
                <div class="list-group-item">
                    <span class="text-muted">Membership status</span>
                    <span class="badge {{ $membership->status === 'active' ? 'badge-success' : 'badge-warning' }}">{{ ucfirst($membership->status) }}</span>
                </div>
                <div class="list-group-item">
                    <span class="text-muted">Joined</span>
                    <span>{{ $membership->joined_at?->diffForHumans() ?? 'N/A' }}</span>
                </div>
                <div class="list-group-item">
                    <span class="text-muted">Last login</span>
                    <span>{{ $membership->user->last_login_at?->diffForHumans() ?? 'Never' }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Manage Roles</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('tenant.users.update', [$tenant, $membership]) }}">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        @foreach(['active', 'suspended', 'inactive'] as $status)
                        <option value="{{ $status }}" {{ $membership->status === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Roles</label>
                    @foreach(\SaasFoundation\Models\Role::where('tenant_id', $tenant->id)->get() as $role)
                    <label class="form-check" style="margin-bottom:8px;">
                        <input type="checkbox" name="roles[]" value="{{ $role->id }}" {{ $membership->roles->contains('id', $role->id) ? 'checked' : '' }}>
                        {{ $role->name }}
                    </label>
                    @endforeach
                </div>

                <button type="submit" class="btn btn-primary">Save changes</button>
            </form>
        </div>
    </div>
</div>

@if($activity->isNotEmpty())
<div class="card" style="margin-top:24px;">
    <div class="card-header"><h3>Recent Activity in this Tenant</h3></div>
    <div class="card-body" style="padding:0;">
        @foreach($activity as $event)
        <div class="list-group-item">
            <div>
                <div style="font-size:14px;">{{ $event->description }}</div>
                <div class="text-muted text-sm">{{ $event->created_at->diffForHumans() }}</div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif
@endsection