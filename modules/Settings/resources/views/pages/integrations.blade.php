@extends('core::layouts.app', [
    'pageTitle' => 'Integrations',
    'pageSub' => 'Account-level configuration · '.(count($enabled) > 0 ? count($enabled).' connected' : 'no connections yet'),
])

@section('content')
<div class="list-page" style="max-width: 1000px;">
    <div class="list-header">
        <div>
            <div class="list-title">Integrations</div>
            <div class="list-subtitle">Account-level configuration for {{ auth()->user()?->currentMembership?->tenant?->name ?? 'this tenant' }}. Enabled integrations apply org-wide.</div>
        </div>
    </div>

    <form method="POST" action="{{ route('deally.integrations.update') }}">
        @csrf
        <div class="settings-section">
            @foreach ($catalog as $key => $integration)
                <div style="display:flex; align-items:flex-start; gap:14px; padding:14px 0; border-bottom:1px solid var(--border-soft);{{ $loop->first ? 'padding-top:4px;' : '' }}">
                    <div style="width:40px;height:40px;border-radius:10px;background:var(--surface-2);border:1px solid var(--border);display:grid;place-items:center;font-size:18px;flex-shrink:0;">{{ $integration['icon'] }}</div>
                    <div style="flex:1; min-width:0;">
                        <div style="font-weight:500;color:var(--text);">{{ $integration['name'] }}</div>
                        <div style="font-size:12px;color:var(--text-3);margin-top:2px;">{{ $integration['description'] }}</div>
                    </div>
                    <label class="toggle-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;flex-shrink:0;">
                        <input type="checkbox" name="enabled[]" value="{{ $key }}" @checked(in_array($key, $enabled, true))>
                        <span style="font-size:12px;color:var(--text-2);">{{ in_array($key, $enabled, true) ? 'Connected' : 'Off' }}</span>
                    </label>
                </div>
            @endforeach
            <div style="display:flex; gap:10px; margin-top:18px;">
                <button class="btn-sm primary" type="submit">Save Integrations</button>
            </div>
        </div>
    </form>
</div>
@endsection