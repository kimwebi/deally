@extends('layouts.app')
@php $pageTitle = 'Tenants'; @endphp

@section('content')
<div class="page-header">
    <h1>Tenants</h1>
    <a href="{{ route('central.tenants.create') }}" class="btn btn-primary">Create Tenant</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('central.tenants.index') }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:end;">
            <div class="form-group" style="margin-bottom:0; flex:1; min-width:200px;">
                <label class="form-label">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name or slug...">
            </div>
            <div class="form-group" style="margin-bottom:0; min-width:150px;">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">All</option>
                    @foreach(['pending','provisioning','active','trial','suspended','inactive','archived'] as $status)
                    <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
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
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Status</th>
                        <th>Members</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tenants as $tenant)
                    <tr>
                        <td>
                            <a href="{{ route('central.tenants.show', $tenant) }}">{{ $tenant->name }}</a>
                        </td>
                        <td class="mono text-muted">{{ $tenant->slug }}</td>
                        <td>
                            @php
                            $badgeClass = match($tenant->status) {
                                'active' => 'badge-success',
                                'trial' => 'badge-primary',
                                'suspended' => 'badge-warning',
                                default => 'badge-gray',
                            };
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ ucfirst($tenant->status) }}</span>
                        </td>
                        <td>{{ $tenant->memberships_count }}</td>
                        <td class="text-muted text-sm">{{ $tenant->created_at->diffForHumans() }}</td>
                        <td class="text-right">
                            <div class="flex gap-2" style="justify-content:flex-end;">
                                <form method="POST" action="{{ route('central.tenants.clone', $tenant) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm">Clone</button>
                                </form>
                                <a href="{{ route('central.tenants.edit', $tenant) }}" class="btn btn-secondary btn-sm">Edit</a>
                                <form method="POST" action="{{ route('central.tenants.destroy', $tenant) }}" onsubmit="return confirm('Are you sure you want to delete this tenant?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" name="confirm" value="1" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted" style="padding:32px;">
                            No tenants found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($tenants->hasPages())
    <div class="card-footer">
        {{ $tenants->links() }}
    </div>
    @endif
</div>
@endsection