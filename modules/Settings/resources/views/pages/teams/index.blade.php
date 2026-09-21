@extends('core::layouts.app', [
    'pageTitle' => 'Teams',
    'pageSub' => 'Organize agents and see what each member works on',
])

@section('content')
<div class="list-page">
    <div class="list-header">
        <div>
            <div class="list-title">Teams</div>
            <div class="list-subtitle">{{ $teams->count() }} teams · {{ auth()->user()->currentMembership?->tenant?->name }}</div>
        </div>
        <button class="btn-sm primary" data-open-modal="team-create">＋ New Team</button>
    </div>

    <table class="data-table">
        <thead>
            <tr><th>Team</th><th>Members</th><th>Description</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($teams as $team)
                <tr>
                    <td class="primary">{{ $team->name }}</td>
                    <td>
                        @if($team->members->isEmpty())
                            <span style="color:var(--text-3);">No members</span>
                        @else
                            {{ $team->members->pluck('name')->join(', ') }}
                        @endif
                    </td>
                    <td>{{ $team->description ?: '—' }}</td>
                    <td style="text-align:right;">
                        <button class="row-action primary" style="background:none;border:none;cursor:pointer;font-size:13px;"
                                data-open-modal="team-edit"
                                data-name="{{ $team->name }}"
                                data-description="{{ $team->description ?? '' }}"
                                data-action="{{ route('deally.teams.update', $team) }}">Edit</button>
                        <form method="POST" action="{{ route('deally.teams.destroy', $team) }}" onsubmit="return confirm('Delete this team?');" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="row-action danger" style="background:none;border:none;cursor:pointer;font-size:13px;">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center; color: var(--text-3);">No teams yet. Create one to organize your sales floor.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@push('modals')
<div class="modal-overlay" id="team-create">
    <div class="modal-card">
        <div class="edit-head">
            <div class="list-title">New Team</div>
            <button class="edit-close" data-close-modal>×</button>
        </div>
        <form method="POST" action="{{ route('deally.teams.store') }}">
            @csrf
            <div class="field-block">
                <div class="field-label">Team name</div>
                <input class="input-field" name="name" required>
            </div>
            <div class="field-block">
                <div class="field-label">Description</div>
                <textarea class="input-field" name="description" rows="2"></textarea>
            </div>
            <div class="field-block">
                <div class="field-label">Members</div>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    @foreach ($members as $id => $name)
                        <label style="display:flex; gap:8px; align-items:center; font-size:14px;">
                            <input type="checkbox" name="members[]" value="{{ $id }}"> {{ $name }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div style="display:flex; gap:10px; margin-top:18px;">
                <button class="btn-sm primary" type="submit">Create Team</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="team-edit">
    <div class="modal-card">
        <div class="edit-head">
            <div class="list-title">Edit Team</div>
            <button class="edit-close" data-close-modal>×</button>
        </div>
        <form method="POST" action="{{ route('deally.teams.update', 0) }}" id="team-edit-form">
            @csrf
            @method('PUT')
            <div class="field-block">
                <div class="field-label">Team name</div>
                <input class="input-field" name="name" data-fill="name" required>
            </div>
            <div class="field-block">
                <div class="field-label">Description</div>
                <textarea class="input-field" name="description" rows="2" data-fill="description"></textarea>
            </div>
            <div style="display:flex; gap:10px; margin-top:18px;">
                <button class="btn-sm primary" type="submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-open-modal="team-edit"]');
        if (!trigger) return;
        var form = document.getElementById('team-edit-form');
        if (form) form.action = trigger.getAttribute('data-action');
    });
</script>
@endpush
@endsection