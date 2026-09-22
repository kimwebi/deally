@extends('core::layouts.app', [
    'pageTitle' => 'Team Workspace',
    'pageSub' => 'Team view · '.$rosterCount.' reps · '.$callsToday.' calls today',
])

@section('content')
<div class="workspace">
    <div class="pane pane-left">
        <div class="section-label"><span class="lbl-left"><span class="dot"></span>Team Pulse</span></div>
        <div class="kpi-row">
            <div class="kpi-card primary">
                <div class="kpi-value">{{ $kpis['open']['value'] }}</div>
                <div class="kpi-label">Open Pipeline</div>
                <div class="kpi-delta up">{{ $kpis['open']['sub'] }} · {{ $kpis['open']['delta'] }}</div>
            </div>
            <div class="kpi-card green">
                <div class="kpi-value">{{ $kpis['closeRate']['value'] }}</div>
                <div class="kpi-label">Close Rate</div>
                <div class="kpi-delta">{{ $kpis['closeRate']['sub'] }} · {{ $kpis['closeRate']['delta'] }}</div>
            </div>
        </div>

        <div class="section-label"><span class="lbl-left"><span class="dot" style="background:var(--danger)"></span>At-Risk Deals</span><span class="section-count">{{ $atRisk->count() }}</span></div>

        @forelse ($atRisk as $deal)
            <div class="risk-deal">
                <div class="risk-deal-title">{{ $deal['title'] }} <span style="color:var(--danger-bright);font-size:11px">{{ $deal['value'] }}</span></div>
                <div class="risk-deal-meta"><span>{{ $deal['owner'] }}</span></div>
                <div class="risk-reason">{{ $deal['reason'] }}</div>
            </div>
        @empty
            <div style="font-size:12px;color:var(--text-3);text-align:center;padding:14px 0;">No at-risk deals right now.</div>
        @endforelse

        <div class="section-label" style="margin-top:22px;"><span class="lbl-left"><span class="dot"></span>Rep Leaderboard</span><span class="section-count">{{ $leaderboard->count() }}</span></div>

        @forelse ($leaderboard as $index => $rep)
            <div class="rep-row">
                <div class="rep-avatar" style="background:{{ $avatarColors[$index] ?? 'linear-gradient(135deg,var(--primary),#0077B5)' }}">{{ $rep['initials'] }}</div>
                <div class="rep-info"><div class="rep-name">{{ $rep['name'] }}</div><div class="rep-stat">{{ $rep['stat'] }}</div></div>
                <div class="rep-trend {{ $rep['trend'] }}">{{ $rep['trend'] === 'up' ? '▲' : '▼' }}</div>
            </div>
        @empty
            <div style="font-size:12px;color:var(--text-3);text-align:center;padding:14px 0;">No rep activity yet.</div>
        @endforelse
    </div>

    <div class="pane pane-centre">
        <div class="cal-panel">
            <div class="cal-top">
                <div>
                    <div class="cal-title-big">Team Calendar · Today</div>
                    <div class="cal-subtitle">{{ $callsToday }} calls today · {{ $rosterCount }} reps</div>
                    <div class="cal-legend">
                        @foreach ($leaderboard as $index => $rep)
                            <span><span class="legend-dot {{ $legendDots[$index] ?? 'team-a' }}"></span>{{ $rep['first'] }}</span>
                        @endforeach
                    </div>
                </div>
                <div class="cal-actions">
                    <button class="cal-action-btn" type="button" data-open-modal="modal-event">＋ Event</button>
                    <button class="cal-action-btn primary" type="button" data-open-modal="modal-task">＋ Task</button>
                </div>
            </div>

            <div class="cal-day-view">
                @php $currentHour = (int) now()->format('H'); @endphp
                @foreach (['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'] as $slot)
                    @php
                        $hour = (int) substr($slot, 0, 2);
                        $events = $teamEvents->filter(fn ($e) => $e['hour'] === $hour)->sortBy('time')->values();
                    @endphp
                    <div class="hour-row">
                        <div class="hour-mark">{{ $slot }}</div>
                        <div class="hour-slot-area">
                            @foreach ($events as $event)
                                <a href="{{ $event['href'] }}" class="cal-event-v2 {{ $event['color'] }}">
                                    <span>⚡</span><span class="event-label">{{ $event['label'] }}</span><span class="event-time">{{ $event['time'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                    @if ($hour === $currentHour)
                        <div class="now-line"><div class="now-dot"></div><div class="now-line-bar"></div><div class="now-label">{{ now()->format('H:i') }}</div></div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    <div class="pane pane-right">
        <div class="section-label"><span class="lbl-left"><span class="dot"></span>Awaiting Approval</span><span class="section-count">{{ $approvals->count() }}</span></div>

        @forelse ($approvals as $approval)
            <div class="approval-card">
                <div class="approval-title">{{ $approval['title'] }}</div>
                <div class="approval-meta">{{ $approval['meta'] }}</div>
                <div class="approval-actions">
                    <a class="approve" href="{{ $approval['href'] }}">Open</a>
                </div>
            </div>
        @empty
            <div style="font-size:12px;color:var(--text-3);text-align:center;padding:14px 0;">Nothing awaiting approval.</div>
        @endforelse

        <div class="section-label" style="margin-top:22px;"><span class="lbl-left"><span class="dot" style="background:var(--warning)"></span>Coaching Flags</span><span class="section-count">{{ $flags->count() }}</span></div>

        @forelse ($flags as $flag)
            <div class="coach-flag">
                <div class="coach-icon {{ $flag['icon'] }}">{{ $flag['icon'] === 'ai' ? '✦' : '⚠' }}</div>
                <div><div class="coach-title">{{ $flag['title'] }}</div><div class="coach-meta">{{ $flag['meta'] }}</div></div>
            </div>
        @empty
            <div style="font-size:12px;color:var(--text-3);text-align:center;padding:14px 0;">No coaching flags today.</div>
        @endforelse

        <div class="section-label" style="margin-top:22px;"><span class="lbl-left"><span class="dot"></span>Quick Links</span></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="cal-action-btn deally" href="{{ route('deally.reporting') }}">Team Dashboard</a>
            <a class="cal-action-btn task" href="{{ route('deally.reporting.tasks') }}">Team Tasks</a>
            <a class="cal-action-btn" href="{{ route('deally.pipeline') }}">Pipeline</a>
        </div>
    </div>
</div>
@endsection

@push('modals')
@include('core::partials.quick-add-modals')
@endpush