@extends('core::layouts.app', [
    'pageTitle' => $company,
    'pageSub' => 'Account story · '.$calls->count().' conversations · '.$tasks->count().' tasks',
])

@section('content')
<div class="list-page" style="max-width: 960px;">
    <div class="report-hero">
        <div>
            <div class="report-hero-title">{{ $company }}</div>
            <div class="report-hero-sub">Deal context for this customer — conversation summaries, sentiment, open items, and risk flags.</div>
        </div>
        <div class="report-hero-actions">
            @if ($opportunity)
                <a href="{{ route('deally.pipeline', ['stage' => $opportunity->stage]) }}" class="btn-sm">Pipeline</a>
            @endif
            <a href="{{ route('deally.reporting') }}" class="btn-sm primary">Team Dashboard</a>
        </div>
    </div>

    <div class="metric-grid">
        @foreach ($keyNumbers as $label => $value)
            <div class="metric-card static">
                <div class="metric-label">{{ $label }}</div>
                <div class="metric-value">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    @if ($flags->isNotEmpty())
        <div class="risk-panel">
            <div class="report-panel-title">⚠️ Deal risk flags</div>
            <div class="risk-list">
                @foreach ($flags as $flag)
                    <div class="risk-item">{{ $flag }}</div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="report-grid">
        <div class="report-panel">
            <div class="report-panel-title">Recent conversation summaries</div>
            <div class="report-panel-sub">Latest insights, verbatim from DeAlly's post-call summaries</div>

            @forelse ($calls->take(5) as $call)
                <div class="story-convo">
                    <div class="story-convo-head">
                        <span class="story-convo-name">{{ $call->name }}</span>
                        <span class="story-convo-date">{{ $call->date->format('M d, Y') }} · {{ $call->duration ?: '0m' }}</span>
                    </div>
                    <div class="story-convo-body">{{ $call->summary ?: 'No summary generated.' }}</div>
                </div>
            @empty
                <div style="font-size: 12px; color: var(--text-3);">No conversations recorded for this account.</div>
            @endforelse

            @if ($calls->isNotEmpty())
                <a href="{{ route('deally.calls.index') }}" class="btn-sm" style="margin-top: 10px;">All conversations</a>
            @endif
        </div>

        <div class="report-panel">
            <div class="report-panel-title">Sentiment over time</div>
            <div class="report-panel-sub">Per-conversation tone, most recent first</div>

            @forelse ($calls->sortByDesc('date')->take(8) as $call)
                @php
                    $tone = match ($call->sentiment) {
                        'positive' => 'good',
                        'negative' => 'warn',
                        default => '',
                    };
                @endphp
                <div class="score-row">
                    <span class="score-label">{{ $call->date->format('M d') }} · {{ $call->name }}</span>
                    <span class="score-value {{ $tone }}">{{ ucfirst($call->sentiment ?? 'neutral') }}</span>
                </div>
            @empty
                <div style="font-size: 12px; color: var(--text-3);">No sentiment data yet.</div>
            @endforelse
        </div>
    </div>

    <div class="report-grid">
        <div class="report-panel">
            <div class="report-panel-title">Unresolved items</div>
            <div class="report-panel-sub">Open tasks and pending knowledge gaps for this account</div>

            @forelse ($unresolved->take(8) as $item)
                <div class="gap-log-row">
                    <div class="gap-log-icon {{ get_class($item) === \Deally\Tasks\Models\Task::class ? 'gap' : ($item->type === 'correction' ? 'correction' : 'gap') }}">
                        {{ get_class($item) === \Deally\Tasks\Models\Task::class ? '📋' : '❓' }}
                    </div>
                    <div class="gap-log-body">
                        <div class="gap-log-title">{{ $item->title ?? $item->text }}</div>
                        <div class="gap-log-meta">
                            {{ isset($item->due_at) && $item->due_at ? 'Due '.$item->due_at->format('M d, g:ia').' · ' : '' }}{{ isset($item->status) ? ucfirst($item->status) : 'Pending' }}
                        </div>
                    </div>
                </div>
            @empty
                <div style="font-size: 12px; color: var(--text-3);">All clear — nothing unresolved.</div>
            @endforelse
        </div>

        <div class="report-panel">
            <div class="report-panel-title">Key numbers</div>
            <div class="report-panel-sub">Proposal and engagement health</div>

            @foreach ($keyNumbers as $label => $value)
                <div class="score-row">
                    <span class="score-label">{{ $label }}</span>
                    <span class="score-value">{{ $value }}</span>
                </div>
            @endforeach

            @forelse ($proposals as $proposal)
                <div class="gap-log-row" style="margin-top: 8px;">
                    <div class="gap-log-icon {{ $proposal->status === 'rejected' ? 'correction' : 'gap' }}">📄</div>
                    <div class="gap-log-body">
                        <div class="gap-log-title">{{ $proposal->name }}</div>
                        <div class="gap-log-meta">${{ number_format($proposal->value) }} · {{ ucfirst($proposal->status) }}</div>
                    </div>
                </div>
            @empty
                <div style="font-size: 12px; color: var(--text-3);">No proposals for this account.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection