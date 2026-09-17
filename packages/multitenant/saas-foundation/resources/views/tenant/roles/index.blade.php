@extends('layouts.app')
@php $pageTitle = 'Roles'; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('tenant.dashboard', $tenant) }}">{{ $tenant->name }}</a>
    <span>/</span>
    <span>Roles</span>
</div>

<div class="page-header">
    <h1>Roles</h1>
    <a href="{{ route('tenant.roles.create', $tenant) }}" class="btn btn-primary">Create Role</a>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Permissions</th>
                        <th>Members</th>
                        <th>Type</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($roles as $role)
                    <tr>
                        <td>
                            <a href="{{ route('tenant.roles.show', [$tenant, $role]) }}" style="font-weight:500; color:#111827;">{{ $role->name }}</a>
                            @if($role->is_system)
                            <span class="badge badge-primary">System</span>
                            @endif
                        </td>
                        <td>{{ $role->permissions->count() }} permissions</td>
                        <td>{{ $role->memberships_count }}</td>
                        <td>
                            <span class="badge {{ $role->is_system ? 'badge-primary' : 'badge-gray' }}">{{ $role->is_system ? 'System' : 'Custom' }}</span>
                        </td>
                        <td class="text-right">
                            <div class="flex gap-2" style="justify-content:flex-end;">
                                <a href="{{ route('tenant.roles.show', [$tenant, $role]) }}" class="btn btn-secondary btn-sm">View</a>
                                @if(!$role->is_system)
                                <a href="{{ route('tenant.roles.edit', [$tenant, $role]) }}" class="btn btn-secondary btn-sm">Edit</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted" style="padding:32px;">No roles found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection