@extends('core::layouts.app', [
    'pageTitle' => 'Deactivate '.$membership->user->name,
    'pageSub' => 'Confirm removal',
])

@section('content')
<div class="list-page" style="max-width: 780px;">
    <div class="list-header">
        <div>
            <div class="list-title">Deactivate {{ $membership->user->name }}</div>
            <div class="list-subtitle">
                {{ ucfirst($membership->status) }} member · {{ $ownedCount }} customer record(s) owned
            </div>
        </div>
        <a href="{{ route('deally.users.index') }}" class="btn-sm">← Users</a>
    </div>

    <div class="settings-section">
        <div class="settings-title">No reassignment plan</div>
        @if ($seat)
            <div class="settings-sub">
                Team Leader and Solutions Lead seats are filled by the tenant admin. {{ $membership->user->name }}'s
                {{ $ownedCount }} customer record(s) stay put until you assign someone from the people page to take
                them over — ownership is never cascaded automatically for seat roles.
            </div>
        @else
            <div class="settings-sub">
                This member is not a sales agent and owns {{ $ownedCount }} customer record(s). Their records are not
                transferred on removal.
            </div>
        @endif

        <div style="display: flex; gap: 10px; margin-top: 16px; align-items: center;">
            <form method="POST" action="{{ route('deally.users.confirm-removal', $membership) }}"
                  onsubmit="return confirm('Remove {{ $membership->user->name }} from this tenant?');">
                @csrf
                @method('DELETE')
                <button class="btn-sm" style="color: var(--danger-bright);" type="submit">Remove from tenant</button>
            </form>
            <a href="{{ route('deally.users.index') }}" class="btn-sm">Cancel</a>
        </div>
    </div>
</div>
@endsection