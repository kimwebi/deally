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
                <details class="settings-row" style="border:1px solid var(--border);border-radius:10px;padding:14px 16px;margin-bottom:10px;" {{ $loop->first ? 'open' : '' }}>
                    <summary style="cursor:pointer;list-style:none;display:flex;align-items:center;justify-content:space-between;gap:12px;">
                        <span style="font-weight:600;">{{ $group }} <small style="color:var(--text-3);">({{ $groupPermissions->count() }} permissions)</small></span>
                        <span style="color:var(--text-3);font-size:13px;"><i class="bi bi-caret-down-fill"></i> </span>
                    </summary>
                    <div style="margin-top:12px;display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:8px 16px;">
                        @foreach ($groupPermissions as $permission)
                            <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
                                <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->permissions->contains($permission))>
                                <span>{{ $permission->name }} <small style="color:var(--text-3);display:block;">{{ $permission->slug }}</small></span>
                            </label>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>

        <div style="display: flex; gap: 10px; margin-top: 18px;">
            <button class="btn-sm primary" type="submit">Save Changes</button>
            <a href="{{ route('deally.roles.index') }}" class="btn-sm">Cancel</a>
        </div>
    </form>
</div>
@endsection
