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
                <div class="settings-row-label">
                    Notifications
                    <div style="font-size: 12px; color: var(--text-3);">Which notification categories the company receives. Set at company level — not per person.</div>
                </div>
                <span class="settings-row-value">
                    {{ collect($notificationSettings)->filter()->keys()->map(fn ($key) => match ($key) {
                        'expert_gaps' => 'Expert Answers queue',
                        'call_reports' => 'Call reports',
                        default => ucfirst($key),
                    })->implode(', ') ?: 'None enabled' }}
                </span>
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
@if ($canManageAccount)
            <div style="border-top: 1px solid var(--border-soft); margin-top: 16px; padding-top: 16px;">
                <div class="settings-title" style="margin-bottom: 6px;">Notifications</div>
                <div style="font-size: 12px; color: var(--text-3); margin-bottom: 12px;">
                    Decided for the whole company. If a category is off, nobody in the company receives it.
                </div>
                @foreach ([
                    'expert_gaps' => 'Expert Answers queue',
                    'call_reports' => 'Call reports',
                ] as $key => $label)
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-2); margin-bottom: 8px; cursor: pointer;">
                        <input type="hidden" name="notifications[{{ $key }}]" value="0">
                        <input type="checkbox" name="notifications[{{ $key }}]" value="1"
                            {{ ($notificationSettings[$key] ?? false) ? 'checked' : '' }}> {{ $label }}
                    </label>
                @endforeach
            </div>
            <div style="border-top: 1px solid var(--border-soft); margin-top: 16px; padding-top: 16px;">
                <div class="settings-title" style="margin-bottom: 6px;">Demand Accounts</div>
                <div class="field-block">
                    <div class="field-label">Pipeline value threshold ($)</div>
                    <input class="input-field" type="number" name="demand_pipeline_threshold" min="0" step="1" value="{{ $accountSetting->demandThreshold() }}">
                    <div style="font-size: 12px; color: var(--text-3); margin-top: 6px;">
                        A customer with open pipeline value above this threshold counts as a Demand Account in the risk engine and the reassignment plan.
                    </div>
                </div>
            </div>
@endif
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