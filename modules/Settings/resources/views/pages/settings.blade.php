@extends('core::layouts.app', [
    'pageTitle' => 'Settings',
    'pageSub' => 'Profile & preferences',
])

@section('content')
<div class="list-page" style="max-width: 760px;">
    <div class="list-header">
        <div>
            <div class="list-title">Settings</div>
            <div class="list-subtitle">Profile & preferences</div>
        </div>
    </div>

    <div class="settings-section">
        <div class="settings-title">Profile</div>
        <form method="POST" action="{{ route('deally.settings.update') }}">
            @csrf
            @method('PUT')
            <div class="field-block">
                <div class="field-label">Name</div>
                <input class="input-field" name="name" value="{{ $user->name }}" required>
            </div>
            <div class="field-block">
                <div class="field-label">Email</div>
                <input class="input-field" type="email" name="email" value="{{ $user->email }}" required>
            </div>
            <div class="settings-row" style="border-top: 1px solid var(--border-soft); padding-top: 16px;">
                <div class="settings-row-label">Notifications</div>
                <span class="settings-row-value">Enabled</span>
            </div>
            <div class="settings-row">
                <div class="settings-row-label">Tenant</div>
                <span class="settings-row-value">{{ auth()->user()?->currentMembership?->tenant?->name ?? '—' }}</span>
            </div>
<div style="display: flex; gap: 10px; margin-top: 18px;">
                <button class="btn-sm primary" type="submit">Save Changes</button>
            </div>
        </form>
        <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border-soft);">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-sm">Sign out</button>
            </form>
        </div>
    </div>
</div>
@endsection