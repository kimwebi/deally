@extends('layouts.app')
@php $pageTitle = 'Edit Role - ' . $role->name; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('tenant.dashboard', $tenant) }}">{{ $tenant->name }}</a>
    <span>/</span>
    <a href="{{ route('tenant.roles.index', $tenant) }}">Roles</a>
    <span>/</span>
    <span>{{ $role->name }}</span>
</div>

<div class="page-header">
    <h1>Edit Role</h1>
</div>

<div class="card" style="max-width:720px;">
    <div class="card-body">
        <form method="POST" action="{{ route('tenant.roles.update', [$tenant, $role]) }}">
            @csrf
            @method('PUT')

            @if($errors->any())
            <div class="alert alert-danger">
                <ul style="margin-left:20px;">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <div class="form-group">
                <label class="form-label" for="name">Role name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $role->name) }}" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" class="form-control">{{ old('description', $role->description) }}</textarea>
            </div>

            <hr class="separator">

            <h3 class="mb-4">Permissions</h3>

            @foreach($permissions as $group => $groupPermissions)
            <div style="margin-bottom:20px;">
                <div style="font-size:14px; font-weight:600; color:var(--gray-600); text-transform:capitalize; margin-bottom:8px;">{{ $group }}</div>
                <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px,1fr)); gap:8px;">
                    @foreach($groupPermissions as $permission)
                    <label class="form-check">
                        <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" {{ $role->permissions->contains('id', $permission->id) || in_array($permission->id, old('permissions', [])) ? 'checked' : '' }}>
                        <span style="font-size:13px; color:#111827;">{{ $permission->name }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
            @endforeach

            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="{{ route('tenant.roles.show', [$tenant, $role]) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection