@extends('core::layouts.app', [
    'pageTitle' => 'Coaching Review',
    'pageSub' => $call->name.' · '.$call->company.' · '.$call->date->format('M d, Y'),
])

@section('content')
<div class="list-page" style="max-width: 960px;">
    <div class="report-hero">
        <div>
            <div class="report-hero-title">Coaching Review — {{ $call->company }}</div>
            <div class="report-hero-sub">Effectiveness snapshot for this call. Score mirrors the agent's Review Call task.</div>
        </div>
        <div class="report-hero-actions">
            <a href="{{ route('deally.calls.show', $call) }}" class="btn-sm">Transcript</a>
            <a href="{{ route('deally.calls.review', $call) }}" class="btn-sm primary">Deep Review</a>
        </div>
    </div>

    <div class="metric-grid">
        <div class="metric-card static">
            <div class="metric-label">Agent performance score</div>
            <div class="metric-value {{ $score >= 80 ? '' : ($score >= 60 ? 'metric-warn' : 'metric-bad') }}">{{ $score }}<span class="metric-unit">/100</span></div>
            <div class="metric-sub">{{ $score >= 80 ? 'Strong performance' : ($score >= 60 ? 'Room to improve' : 'Needs coaching') }}</div>
        </div>
        <div class="metric-card static">
            <div class="metric-label">Customer talk share</div>
            <div class="metric-value {{ $inBand ? '' : 'metric-warn' }}">{{ $talkRatio }}%<span class="metric-unit">target 40–60%</span></div>
            <div class="metric-sub">{{ $inBand ? 'In the target band' : 'Outside the target band' }}</div>
        </div>
        <div class="metric-card static">
            <div class="metric-label">Objections handled</div>
            <div class="metric-value">{{ $objections->count() }}<span class="metric-unit">logged</span></div>
            <div class="metric-sub">{{ $corrections->count() }} corrections captured</div>
        </div>
    </div>

    <div class="report-grid">
        <div class="report-panel">
            <div class="report-panel-title">Three key moments</div>
            <div class="report-panel-sub">Each links to the exact transcript moment</div>

            @foreach ($moments as $moment)
                <a href="{{ route('deally.calls.show', $call).'#line-'.$moment['sequence'] }}" class="moment-card">
                    <div class="moment-icon">{{ $moment['icon'] }}</div>
                    <div class="moment-body">
                        <div class="moment-label">{{ $moment['label'] }}</div>
                        <div class="moment-desc">{{ $moment['description'] }}</div>
                    </div>
                    <span class="moment-link">Open →</span>
                </a>
            @endforeach
        </div>

        <div class="report-panel">
            <div class="report-panel-title">Areas to improve</div>
            <div class="report-panel-sub">Auto-generated from this conversation</div>

            <ol class="improve-list">
                @foreach ($areas as $area)
                    <li>{{ $area }}</li>
                @endforeach
            </ol>
        </div>
    </div>

    <div class="report-grid">
        <div class="report-panel">
            <div class="report-panel-title">Key actions missed</div>
            <div class="report-panel-sub">Overdue follow-ups tied to this account</div>

            @forelse ($missed as $task)
                <div class="gap-log-row">
                    <div class="gap-log-icon gap">📋</div>
                    <div class="gap-log-body">
                        <div class="gap-log-title">{{ $task->title }}</div>
                        <div class="gap-log-meta">Due {{ $task->due_at->format('M d, g:ia') }} · {{ ucfirst($task->status) }}</div>
                    </div>
                </div>
            @empty
                <div style="font-size: 12px; color: var(--text-3);">Nothing overdue for this account.</div>
            @endforelse
        </div>

        <div class="report-panel">
            <div class="report-panel-title">Open items</div>
            <div class="report-panel-sub">Every open task tied to {{ $call->company }}</div>

            @forelse ($openTasks->take(6) as $task)
                <div class="score-row">
                    <span class="score-label">{{ $task->title }}</span>
                    <span class="score-value {{ $task->due_at !== null && $task->due_at->isPast() ? 'warn' : '' }}">{{ $task->due_at ? $task->due_at->format('M d, g:ia') : 'no due' }}</span>
                </div>
            @empty
                <div style="font-size: 12px; color: var(--text-3);">No open tasks.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection