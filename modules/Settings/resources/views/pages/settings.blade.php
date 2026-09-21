@extends('core::layouts.app', [
    'pageTitle' => 'Settings',
    'pageSub' => 'Profile & preferences',
])

@section('content')
<div class="list-page" style="max-width: 1000px;">
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
            <div class="field-block">
                <div class="field-label">Timezone</div>
                <select class="input-field" name="timezone">
                    <option value="">Use tenant timezone</option>
                    @foreach ($timezones as $tz)
                        <option value="{{ $tz }}" @selected($user->timezone === $tz)>{{ $tz }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field-block">
                <div class="field-label">Language</div>
                <select class="input-field" name="locale">
                    @foreach ($locales as $code => $label)
                        <option value="{{ $code }}" @selected(($user->locale ?? 'en') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="settings-row" style="border-top: 1px solid var(--border-soft); padding-top: 16px;">
                <div class="settings-row-label">Notifications</div>
                <span class="settings-row-value">Enabled</span>
            </div>
            <div class="settings-row">
                <div class="settings-row-label">Tenant</div>
                <span class="settings-row-value">{{ auth()->user()?->currentMembership?->tenant?->name ?? '—' }}</span>
            </div>
            <div class="settings-row">
                <div class="settings-row-label">Your seat</div>
                <span class="settings-row-value">
                    {{ $membership && $membership->roles->isNotEmpty() ? $membership->roles->map(fn ($role) => $role->name)->unique()->implode(', ') : '—' }}
                </span>
            </div>
            <div class="settings-row">
                <div class="settings-row-label">Your team</div>
                <span class="settings-row-value">
                    {{ $membership ? ($user->teams->pluck('name')->implode(', ') ?: 'Not assigned') : '—' }}
                </span>
            </div>
            <div class="settings-row">
                <div class="settings-row-label">
                    Data Retention tier
                    <div style="font-size: 12px; color: var(--text-3);">Closed-deal transcripts &amp; proposals are archived after the retention window. Only DeAlly Platform Support can change it.</div>
                </div>
                <span class="settings-row-value">{{ app(\Deally\Retention\Services\RetentionService::class)->tierLabel() }}</span>
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