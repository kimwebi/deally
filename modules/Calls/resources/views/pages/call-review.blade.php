@extends('core::layouts.call', ['title' => 'Review · '.$call->company])

@section('content')
@php
    $sentiment = $call->sentiment ?: 'neutral';
    $readiness = $sentiment === 'positive'
        ? ['score' => 'Very ready', 'class' => 'good', 'chip' => 'hot']
        : ($sentiment === 'neutral' ? ['score' => 'Somewhat ready', 'class' => 'warn', 'chip' => 'warm'] : ['score' => 'Needs work', 'class' => 'warn', 'chip' => 'cold']);

    $customerLines = $call->transcriptLines->where('is_agent', false)->count();
    $agentLines = $call->transcriptLines->count() - $customerLines;
    $totalLines = max(1, $call->transcriptLines->count());
    $customerPct = round($customerLines / $totalLines * 100);
    $agentPct = 100 - $customerPct;

    $objections = $gaps->whereIn('type', ['objection', 'gap']);
    $objectionTotal = $objections->count();
    $objectionHandled = $objections->where('status', 'live')->count();
    $corrections = $gaps->where('type', 'correction');
@endphp
<div class="review-shell">
    <div class="review-header">
        <a class="review-back" href="{{ route('deally.calls.index') }}">← Calls</a>
        <div class="review-title-block">
            <div class="t">Review Call — {{ $call->company }}</div>
            <div class="m">{{ $call->name }} · {{ $call->date->format('M j, Y g:ia') }} · {{ $call->duration ?: '0m' }}</div>
        </div>
        <div class="review-header-actions">
            <a class="btn-sm" href="{{ route('deally.calls.summary', $call) }}">Summary</a>
            <a class="btn-sm" href="{{ route('deally.calls.show', $call) }}">Transcript</a>
            <a class="btn-sm primary" href="{{ route('deally.calls.transcript.download', $call) }}">⬇ Download transcript</a>
        </div>
    </div>

    <div class="review-body">
        {{-- Transcript --}}
        <div class="review-transcript">
            <div class="section-label"><span class="lbl-left"><span class="dot"></span>Transcript</span><span class="section-count">{{ $totalLines }} lines</span></div>

            @forelse ($call->transcriptLines as $line)
                <div class="transcript-line">
                    <div class="speaker {{ $line->is_agent ? 'agent' : '' }}">{{ $line->is_agent ? 'Agent' : $line->speaker }}</div>
                    <div class="transcript-text">{{ $line->text }}</div>
                </div>
                @if ($line->linked_text)
                    <div class="transcript-linked {{ $line->linked_type === 'competitor' ? 'competitor' : '' }}">
                        {{ $line->linked_type === 'competitor' ? '🔵 Battle card shown · ' : '⚪ ' }}{{ $line->linked_text }}
                    </div>
                @endif
            @empty
                <div class="brief-body">No transcript lines saved yet — the live session captures them as you go.</div>
            @endforelse
        </div>

        {{-- Structured review --}}
        <div class="review-panel">
            <div class="panel-section">
                <div class="panel-section-label"><span>Sentiment</span></div>
                <div class="panel-chip-row">
                    <span class="panel-chip {{ $sentiment === 'positive' ? 'selected positive' : '' }}">😊 Positive</span>
                    <span class="panel-chip {{ $sentiment === 'neutral' ? 'selected neutral' : '' }}">😐 Neutral</span>
                    <span class="panel-chip {{ $sentiment === 'negative' ? 'selected negative' : '' }}">😞 Negative</span>
                </div>
                <div class="ai-read-note">AI read: {{ ucfirst($sentiment) }}</div>
            </div>

            <div class="panel-section">
                <div class="panel-section-label"><span>Close-Readiness</span></div>
                <div class="panel-chip-row">
                    <span class="panel-chip {{ $readiness['chip'] === 'hot' ? 'selected hot' : '' }}">🔥 Hot</span>
                    <span class="panel-chip {{ $readiness['chip'] === 'warm' ? 'selected warm' : '' }}">🌤 Warm</span>
                    <span class="panel-chip {{ $readiness['chip'] === 'cold' ? 'selected cold' : '' }}">❄ Cold</span>
                </div>
                <div class="ai-read-note">AI read: {{ $readiness['score'] }}</div>
            </div>

            <div class="panel-section">
                <div class="panel-section-label"><span>Agent Performance</span></div>
                <div class="score-card">
                    <div class="score-row"><span class="score-label">Talk ratio</span><span class="score-value {{ $agentPct >= 50 ? 'warn' : 'good' }}">{{ $agentPct }}% agent</span></div>
                    <div class="talk-bar"><div class="talk-bar-agent" style="flex: {{ $agentPct }};"></div><div class="talk-bar-customer" style="flex: {{ $customerPct }};"></div></div>
                    <div class="score-row"><span class="score-label">Objections handled</span><span class="score-value {{ $objectionHandled >= $objectionTotal && $objectionTotal > 0 ? 'good' : 'warn' }}">{{ $objectionTotal > 0 ? $objectionHandled.' of '.$objectionTotal : 'None logged' }}</span></div>
                    <div class="score-row"><span class="score-label">Follow-ups captured</span><span class="score-value">{{ $totalLines === 1 ? '1 line' : $totalLines.' lines' }}</span></div>
                    <div class="score-row"><span class="score-label">Overall</span><span class="score-value">{{ $readiness['score'] }}</span></div>
                </div>
            </div>

            <div class="panel-section">
                <div class="panel-section-label"><span>Objection Log</span><span style="font-size:10px;color:var(--text-4)">{{ $objectionTotal }} items</span></div>
                @forelse ($objections as $gap)
                    <div class="objection-item">
                        <span>{{ $gap->text }}</span>
                        <span class="objection-tag {{ $gap->status === 'live' ? 'answered' : 'gap' }}">{{ $gap->status === 'live' ? 'Answered' : 'Gap' }}</span>
                    </div>
                @empty
                    <div class="brief-body">No objections or gaps logged.</div>
                @endforelse
            </div>

            <div class="panel-section">
                <div class="panel-section-label"><span>Corrections</span></div>
                <div style="display:flex;flex-direction:column;gap:8px">
                    @forelse ($corrections as $gap)
                        <div style="background:var(--surface);border:1px solid var(--border-soft);border-radius:var(--r-md);padding:10px 12px;font-size:12px;display:flex;justify-content:space-between;align-items:center;gap:10px">
                            <span style="color:var(--text-3)">{{ $gap->text }}</span>
                            <span style="color:var(--text-4);font-weight:500;text-transform:capitalize;flex-shrink:0">{{ $gap->status === 'live' ? '✓ Correct' : ($gap->status === 'rejected' ? '✕ Skipped' : 'Needs review') }}</span>
                        </div>
                    @empty
                        <div class="brief-body">No corrections this call.</div>
                    @endforelse
                </div>
            </div>

            <div class="panel-section">
                <a class="btn-proposal" href="{{ route('deally.proposals.index') }}">📄 Create Proposal</a>
                <div style="font-size:11px;color:var(--text-4);text-align:center">Detected intent: a proposal for {{ $call->company }} can be drafted from this call</div>
            </div>
        </div>
    </div>
</div>
@endsection