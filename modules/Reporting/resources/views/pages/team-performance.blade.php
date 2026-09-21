@extends('core::layouts.app', [
    'pageTitle' => 'Team Performance',
    'pageSub' => 'Dashboard · '.now()->format('F Y').' · '.$calls->count().' conversations analyzed',
])

@section('content')
<div class="list-page">
    <div class="report-hero">
        <div>
            <div class="report-hero-title">Team Performance</div>
            <div class="report-hero-sub">Every metric below is computed from real deal, call, and task data. Click any card to drill into the underlying records.</div>
        </div>
        <div class="report-hero-actions">
            <a href="{{ route('deally.reporting.tasks') }}" class="btn-sm">Team Tasks</a>
            <a href="{{ route('deally.pipeline') }}" class="btn-sm primary">Open Pipeline</a>
        </div>
    </div>

    <div class="metric-grid">
        @foreach ($kpis as $key => $kpi)
            <a href="{{ $kpi['href'] }}" class="metric-card">
                <div class="metric-label">{{ ucwords(str_replace('_', ' ', $key)) }}</div>
                <div class="metric-value">{{ $kpi['value'] }}</div>
                <div class="metric-sub">{{ $kpi['sub'] }}</div>
            </a>
        @endforeach
    </div>

    <div class="report-grid">
        <div class="report-panel">
            <div class="report-panel-title">AI Effectiveness</div>
            <div class="report-panel-sub">Win rates on deals where DeAlly guidance was used versus not</div>

            <table class="data-table">
                <thead>
                    <tr><th>Guidance</th><th>Deals</th><th>Won</th><th style="text-align:right;">Win rate</th></tr>
                </thead>
                <tbody>
                    @forelse ($aiGuided as $row)
                        <tr>
                            <td class="primary">{{ $row['label'] }}</td>
                            <td>{{ $row['total'] }}</td>
                            <td>{{ $row['won'] }}</td>
                            <td class="mono" style="text-align:right;">{{ $row['rate'] }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="text-align:center; color: var(--text-3);">No closed deals yet to compare.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="report-panel">
            <div class="report-panel-title">Sentiment Trend</div>
            <div class="report-panel-sub">How conversations are trending across the team</div>

            <div class="sentiment-strip">
                @forelse ($calls->sortByDesc('date')->take(14) as $call)
                    @php
                        $tone = match ($call->sentiment) {
                            'positive' => 'sentiment-pos',
                            'negative' => 'sentiment-neg',
                            default => 'sentiment-neu',
                        };
                    @endphp
                    <div class="sentiment-chip {{ $tone }}" title="{{ $call->name }} · {{ $call->date->format('M d') }}">{{ $call->sentiment ? ucfirst(substr($call->sentiment, 0, 3)) : 'Neu' }}</div>
                @empty
                    <div style="font-size: 12px; color: var(--text-3);">No conversations recorded.</div>
                @endforelse
            </div>

            <div class="score-row">
                <span class="score-label">Review backlog</span>
                <span class="score-value {{ $reviewCount > 0 ? 'warn' : 'good' }}">{{ $reviewCount }} open</span>
            </div>
            <div class="score-row">
                <span class="score-label">Archived transcripts</span>
                <span class="score-value">{{ $archivedCount }}</span>
            </div>
        </div>
    </div>
</div>
@endsection
