@extends('core::layouts.app', [
    'pageTitle' => 'Users',
    'pageSub' => 'Add and manage people in this tenant',
])

@section('content')
<div class="list-page">
    <div class="list-header">
        <div>
            <div class="list-title">Users</div>
            <div class="list-subtitle">{{ $members->count() }} member{{ $members->count() === 1 ? '' : 's' }} · {{ auth()->user()->currentMembership?->tenant?->name }}</div>
        </div>
        <button class="btn-sm primary" data-open-modal="user-create">＋ Add User</button>
    </div>

    <table class="data-table">
        <thead>
            <tr><th>Member</th><th>Email</th><th>Roles</th><th>Status</th><th style="text-align:right;"></th></tr>
        </thead>
        <tbody>
            @forelse ($members as $membership)
                <tr>
                    <td class="primary">{{ $membership->user->name }}</td>
                    <td>{{ $membership->user->email }}</td>
                    <td>
                        @if ($membership->roles->isEmpty())
                            <span style="color:var(--text-3);">No roles</span>
                        @else
                            {{ $membership->roles->pluck('name')->join(', ') }}
                        @endif
                    </td>
                    <td><span class="status-pill {{ $membership->status === 'active' ? 'live' : 'rejected' }}">{{ ucfirst($membership->status) }}</span></td>
                    <td style="text-align:right;">
                        <button class="row-action primary" style="background:none;border:none;cursor:pointer;font-size:13px;"
                                data-open-modal="user-edit"
                                data-action="{{ route('deally.users.update', $membership) }}"
                                data-name="{{ $membership->user->name }}"
                                data-roles="{{ $membership->roles->pluck('id')->implode(',') }}">Edit</button>
                        <form method="POST" action="{{ route('deally.users.destroy', $membership) }}" onsubmit="return confirm('Remove {{ $membership->user->name }} from this tenant?');" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="row-action danger" style="background:none;border:none;cursor:pointer;font-size:13px;">Remove</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center; color: var(--text-3);">No members yet. Add your first user to this tenant.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@push('modals')
<div class="modal-overlay" id="user-create">
    <div class="modal-card">
        <div class="edit-head">
            <div class="list-title">Add User</div>
            <button class="edit-close" data-close-modal>×</button>
        </div>
        <form method="POST" action="{{ route('deally.users.store') }}">
            @csrf
            <div class="field-block">
                <div class="field-label">Name</div>
                <input class="input-field" name="name" placeholder="Jane Doe" required>
            </div>
            <div class="field-block">
                <div class="field-label">Email</div>
                <input class="input-field" type="email" name="email" placeholder="jane@example.com" required>
            </div>
            <div class="field-block">
                <div class="field-label">Temporary password</div>
                <input class="input-field" type="password" name="password" minlength="8" placeholder="8+ characters">
                <div style="font-size:12px; color:var(--text-3); margin-top:6px;">Only required when creating a brand new account. Existing users keep theirs.</div>
            </div>
            <div class="field-block">
                <div class="field-label">Roles</div>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    @foreach ($roles as $role)
                        <label style="display:flex; gap:8px; align-items:center; font-size:14px;">
                            <input type="checkbox" name="role_ids[]" value="{{ $role->id }}"> {{ $role->name }}
                            @if ($role->is_system)
                                <span class="status-pill pending">system</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>
            <div style="display:flex; gap:10px; margin-top:18px;">
                <button class="btn-sm primary" type="submit">Add User</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="user-edit">
    <div class="modal-card">
        <div class="edit-head">
            <div class="list-title">Edit User</div>
            <button class="edit-close" data-close-modal>×</button>
        </div>
        <form method="POST" action="{{ route('deally.users.update', 0) }}" id="user-edit-form">
            @csrf
            @method('PUT')
            <div class="field-block">
                <div class="field-label">Member</div>
                <div style="font-size:14px;" data-fill="name">—</div>
            </div>
            <div class="field-block">
                <div class="field-label">Roles</div>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    @foreach ($roles as $role)
                        <label style="display:flex; gap:8px; align-items:center; font-size:14px;">
                            <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" class="user-edit-role"> {{ $role->name }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div style="display:flex; gap:10px; margin-top:18px;">
                <button class="btn-sm primary" type="submit">Save Roles</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-open-modal="user-edit"]');
        if (!trigger) return;

        document.querySelectorAll('.user-edit-role').forEach(function (cb) {
            cb.checked = false;
        });

        var checked = (trigger.getAttribute('data-roles') || '').split(',').filter(Boolean);
        checked.forEach(function (id) {
            var cb = document.querySelector('.user-edit-role[value="' + id + '"]');
            if (cb) cb.checked = true;
        });

        var form = document.getElementById('user-edit-form');
        if (form) form.action = trigger.getAttribute('data-action');
    });
</script>
@endpush
@endsection