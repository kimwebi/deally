@extends('core::layouts.app', [
    'pageTitle' => 'Workspace',
    'pageSub' => today()->format('l, F j') . ' · ' . $opportunities->count() . ' opportunities',
])

@section('content')
<div class="workspace">
    <div class="pane pane-left">
        <div class="section-label"><span class="lbl-left"><span class="dot"></span>Opportunities</span><span class="section-count">{{ $opportunities->count() }}</span></div>

        @forelse ($opportunities as $opportunity)
            <div class="opp-card">
                <div class="opp-top"><div class="opp-name">{{ $opportunity->company }}</div><div class="opp-value">${{ number_format($opportunity->value) }}</div></div>
                <div class="opp-meta">
                    <span>{{ $opportunity->packages ?: 'Standard package' }} · {{ $opportunity->calls_count ?? '' }}</span>
                    <span class="stage-badge stage-{{ $opportunity->stage }}">{{ ucfirst($opportunity->stage) }}</span>
                </div>
            </div>
        @empty
            <div style="font-size: 12px; color: var(--text-3); text-align: center; padding: 20px 0;">No opportunities yet.</div>
        @endforelse

        <div class="section-label" style="margin-top: 22px;"><span class="lbl-left"><span class="dot"></span>Recent Activities</span></div>

        @forelse ($activities as $activity)
            <div class="activity-item">
                <div class="activity-icon {{ $activity['type'] }}">{{ $activity['type'] === 'call' ? '📞' : '📊' }}</div>
                <div class="activity-text">{!! $activity['text'] !!}</div>
                <div class="activity-time">{{ $activity['time'] }}</div>
            </div>
        @empty
            <div style="font-size: 12px; color: var(--text-3); text-align: center; padding: 20px 0;">No recent activity.</div>
        @endforelse
    </div>

    <div class="pane pane-centre">
        <div class="cal-panel">
            <div class="cal-top">
                <div>
                    <div class="cal-title-big">Today, {{ today()->format('F j') }}</div>
                    <div class="cal-subtitle">{{ $callsToday }} events · {{ $deallyCallsToday }} DeAlly call{{ $deallyCallsToday === 1 ? '' : 's' }}</div>
                </div>
                <div class="cal-legend">
                    <span><span class="legend-dot deally"></span>DeAlly</span>
                    <span><span class="legend-dot ext"></span>External</span>
                    <span><span class="legend-dot task"></span>Task</span>
                </div>
            </div>

            <div class="cal-day-view">
                @php $currentHour = (int) now()->format('H'); @endphp
                @foreach (['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'] as $slot)
                    @php
                        $hour = (int) substr($slot, 0, 2);
                        $events = $calendar['events']
                            ->filter(fn ($e) => $e['hour'] === $hour)
                            ->sortBy('hour')
                            ->values();
                    @endphp
                    <div class="hour-row">
                        <div class="hour-mark">{{ $slot }}</div>
                        <div class="hour-slot-area">
                            @foreach ($events as $event)
                                @if ($event['type'] === 'call')
                                    <a href="{{ $event['href'] }}" class="cal-event-v2 deally">
                                        <span>⚡</span><span class="event-label">{{ $event['label'] }}</span><span class="event-time">{{ $event['time'] }}</span>
                                    </a>
                                @elseif ($event['type'] === 'task')
                                    <div class="cal-event-v2 task"><span>📝</span><span class="event-label">{{ $event['label'] }}</span><span class="event-time">{{ $event['time'] }}</span></div>
                                @else
                                    <div class="cal-event-v2 ext"><span>📅</span><span class="event-label">{{ $event['label'] }}</span><span class="event-time">{{ $event['time'] }}</span></div>
                                @endif
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
        <div class="task-section">
            <div class="task-section-header todo"><span>To Do</span><span class="task-count">{{ $todos->count() }}</span></div>
            @forelse ($todos as $task)
                <a href="{{ route('deally.tasks.index') }}" class="task-item-v2">
                    <div class="task-title-v2">{{ $task->title }}</div>
                    <div class="task-meta-v2"><span>{{ $task->linked_company ?: '—' }}</span><span>{{ $task->due_at ? $task->due_at->format('M j') : 'no due' }}</span></div>
                </a>
            @empty
                <div style="font-size: 12px; color: var(--text-3); text-align: center; padding: 10px 0;">All clear.</div>
            @endforelse
        </div>

        <div class="task-section">
            <div class="task-section-header overdue"><span>Overdue</span><span class="task-count">{{ $overdue->count() }}</span></div>
            @forelse ($overdue as $task)
                <a href="{{ route('deally.tasks.index') }}" class="task-item-v2 overdue">
                    <div class="task-title-v2">{{ $task->title }}</div>
                    <div class="task-meta-v2"><span>{{ $task->linked_company ?: '—' }}</span><span>{{ $task->due_at ? $task->due_at->format('M j') : 'no due' }}</span></div>
                </a>
            @empty
                <div style="font-size: 12px; color: var(--text-3); text-align: center; padding: 10px 0;">Nothing overdue.</div>
            @endforelse
        </div>

        <div class="task-section">
            <div class="task-section-header closed"><span>Closed</span><span class="task-count">{{ $closed->count() }}</span></div>
            @forelse ($closed as $task)
                <a href="{{ route('deally.tasks.index') }}" class="task-item-v2 closed">
                    <div class="task-title-v2">{{ $task->title }}</div>
                    <div class="task-meta-v2"><span>{{ $task->linked_company ?: '—' }}</span><span>{{ $task->due_at ? $task->due_at->format('M j') : '—' }}</span></div>
                </a>
            @empty
                <div style="font-size: 12px; color: var(--text-3); text-align: center; padding: 10px 0;">Nothing closed.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection