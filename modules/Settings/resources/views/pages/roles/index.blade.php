@extends('core::layouts.app', [
    'pageTitle' => 'Roles',
    'pageSub' => 'Manage tenant roles & their permissions',
])

@section('content')
<div class="list-page">
    <div class="list-header">
        <div>
            <div class="list-title">Roles</div>
            <div class="list-subtitle">{{ $roles->count() }} roles · {{ auth()->user()->currentMembership?->tenant?->name }}</div>
        </div>
        <a href="{{ route('deally.roles.create') }}" class="btn-sm primary">＋ New Role</a>
    </div>

    <table class="data-table">
        <thead>
            <tr><th>Role</th><th>Permissions</th><th>Members</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($roles as $role)
                <tr>
                    <td class="primary">
                        {{ $role->name }}
                        @if ($role->is_system)
                            <span class="status-pill pending">system</span>
                        @endif
                    </td>
                    <td>{{ $role->permissions->count() }} permissions</td>
                    <td>{{ $role->memberships_count }} member{{ $role->memberships_count === 1 ? '' : 's' }}</td>
                    <td style="text-align:right;">
                        <a href="{{ route('deally.roles.edit', $role) }}" class="row-action primary">Edit</a>
                        @if (! $role->is_system)
                        <form method="POST" action="{{ route('deally.roles.destroy', $role) }}" onsubmit="return confirm('Delete this role?');" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="row-action danger" style="background:none;border:none;cursor:pointer;font-size:13px;">Delete</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center; color: var(--text-3);">No roles yet. Create the first role to start assigning permissions.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection