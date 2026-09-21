@extends('core::layouts.app', [
    'pageTitle' => 'Activity',
    'pageSub' => 'Platform activity & error feed',
])

@section('content')
<div class="list-page">
    <div class="list-header">
        <div>
            <div class="list-title">Activity</div>
            <div class="list-subtitle">{{ auth()->user()->currentMembership?->tenant?->name }} · recent platform activity</div>
        </div>
        <div style="display:flex; gap:8px;">
            @foreach (['all', 'info', 'error'] as $level)
                <a href="{{ route('deally.activity.index', $level === 'all' ? [] : ['level' => $level]) }}"
                   class="btn-sm {{ $activeLevel === $level ? 'primary' : '' }}">{{ ucfirst($level) }}</a>
            @endforeach
        </div>
    </div>

    @if($activities->isEmpty())
        <div style="text-align:center; color:var(--text-3); padding: 40px 0;">No activity recorded yet.</div>
    @endif

    <div class="log-list" style="display:flex; flex-direction:column; gap:10px; margin-top:8px;">
        @foreach ($activities as $activity)
            <div class="gap-log-row">
                <div class="gap-log-icon {{ $activity->properties['level'] ?? 'info' === 'error' ? 'correction' : 'gap' }}">
                    {{ ($activity->properties['level'] ?? 'info') === 'error' ? '⚠️' : '✦' }}
                </div>
                <div class="gap-log-body">
                    <div class="gap-log-title">
                        {{ $activity->event }}
                        <span class="status-pill {{ ($activity->properties['level'] ?? 'info') === 'error' ? 'rejected' : 'pending' }}">
                            {{ $activity->properties['level'] ?? 'info' }}
                        </span>
                    </div>
                    <div class="gap-log-body">{{ $activity->description }}</div>
                    <div class="gap-log-meta">
                        {{ $activity->user?->name ?? 'System' }} · {{ $activity->created_at->diffForHumans() }}
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection