@extends('core::layouts.app', [
    'pageTitle' => 'Edit Role',
    'pageSub' => 'Update '.$role->name.' and its permissions',
])

@section('content')
<div class="list-page">
    <div class="list-header">
        <div>
            <div class="list-title">Edit Role</div>
            <div class="list-subtitle">{{ $role->name }} @if($role->is_system)<span class="status-pill pending">system</span>@endif</div>
        </div>
        <a href="{{ route('deally.roles.index') }}" class="btn-sm">← Back</a>
    </div>

    <form method="POST" action="{{ route('deally.roles.update', $role) }}">
        @csrf
        @method('PUT')
        <div class="settings-section">
            <div class="settings-title">Details</div>
            <div class="settings-row">
                <div class="settings-row-label">Role name</div>
                <div class="settings-row-value">
                    <input class="input-field" name="name" value="{{ old('name', $role->name) }}" required>
                    @error('name')<div style="color: var(--text-3); font-size: 12px; margin-top: 4px;">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="settings-row">
                <div class="settings-row-label">Description</div>
                <div class="settings-row-value">
                    <input class="input-field" name="description" value="{{ old('description', $role->description) }}" placeholder="Optional description">
                </div>
            </div>
        </div>

        <div class="settings-section">
            <div class="settings-title">Permissions</div>
            @foreach ($permissions->groupBy('group_name') as $group => $groupPermissions)
                <div class="settings-row">
                    <div class="settings-row-label">{{ $group }}</div>
                    <div class="settings-row-value">
                        @foreach ($groupPermissions as $permission)
                            <label style="display:flex;align-items:center;gap:8px;margin-bottom:6px;cursor:pointer;">
                                <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->permissions->contains($permission))>
                                <span>{{ $permission->name }} <small style="color:var(--text-3);">{{ $permission->slug }}</small></span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div style="display: flex; gap: 10px; margin-top: 18px;">
            <button class="btn-sm primary" type="submit">Save Changes</button>
            <a href="{{ route('deally.roles.index') }}" class="btn-sm">Cancel</a>
        </div>
    </form>
</div>
@endsection