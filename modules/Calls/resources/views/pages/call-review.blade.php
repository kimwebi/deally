@extends('core::layouts.call', ['title' => 'Review · '.$call->company])

@section('content')
@php
    $sentiment = $call->sentiment ?: 'neutral';
    $readiness = $sentiment === 'positive'
        ? ['score' => 'Very ready', 'class' => 'good']
        : ($sentiment === 'neutral' ? ['score' => 'Somewhat ready', 'class' => 'warn'] : ['score' => 'Needs work', 'class' => 'warn']);

    $customerLines = $call->transcriptLines->where('is_agent', false)->count();
    $agentLines = $call->transcriptLines->count() - $customerLines;
    $totalLines = max(1, $call->transcriptLines->count());
    $customerPct = round($customerLines / $totalLines * 100);
    $agentPct = 100 - $customerPct;
@endphp
<div class="review-shell">
    <div class="review-top">
        <div>
            <div class="review-title">Review Call — {{ $call->company }}</div>
            <div class="review-meta">{{ $call->name }} · {{ $call->date->format('M d, Y') }} · {{ $call->duration ?: '0m' }} · {{ $call->contact_name ?: 'Customer' }}</div>
        </div>
        <div class="review-actions">
            <a class="btn-sm" href="{{ route('deally.calls.summary', $call) }}">Summary</a>
            <a class="btn-sm" href="{{ route('deally.calls.show', $call) }}">Transcript</a>
        </div>
    </div>

    <div class="rview-grid">
        <div class="rview-left">
            <div class="rview-panel">
                <div class="rview-label">1 · Read the transcript</div>
                <div class="transcript-lines">
                    @forelse ($call->transcriptLines as $line)
                        <div class="transcript-line">
                            <div class="speaker {{ $line->is_agent ? 'agent' : '' }}">{{ $line->is_agent ? 'Agent' : $line->speaker }}</div>
                            <div class="transcript-text">{{ $line->text }}</div>
                        </div>
                        @if ($line->linked_text)
                            <div class="transcript-linked {{ $line->linked_type === 'competitor' ? 'competitor' : ($line->linked_type === 'correction' ? 'warning' : '') }}">
                                {{ $line->linked_type === 'competitor' ? '🔵 Battle card shown' : '⚪ '.$line->linked_text }}{{ $line->linked_type === 'competitor' ? '' : '' }}
                            </div>
                        @endif
                    @empty
                        <div class="brief-body">No transcript lines saved yet — the live session captures them as you go.</div>
                    @endforelse
                </div>
            </div>

            <div class="rview-panel">
                <div class="rview-label">3 · Actions live in Tasks</div>
                <div class="obj-log">
                    <div class="obj-log-item">
                        <span>Review Call — {{ $call->company }}</span>
                        <span><a class="btn-sm" href="{{ route('deally.tasks.index') }}">View task</a></span>
                    </div>
                    <div class="brief-body">Knock out the review steps here, then close the task from your Tasks list.</div>
                </div>
            </div>
        </div>

        <div class="rview-right">
            <div class="rview-panel">
                <div class="rview-label">2 · Advisory</div>

                <div class="rview-label" style="margin-bottom: 8px;">Sentiment</div>
                <div class="chip-set">
                    @foreach (['positive' => '😊 Positive', 'neutral' => '😐 Neutral', 'negative' => '😞 Negative'] as $key => $label)
                        <span class="chip-opt {{ $sentiment === $key ? 'selected' : '' }}">{{ $label }}</span>
                    @endforeach
                </div>

                <div class="score-row">
                    <span class="score-label">Sentiment</span>
                    <span class="score-value {{ $sentiment === 'positive' ? 'good' : 'warn' }}">
                        {{ $sentiment === 'positive' ? 'Positive — high intent' : ($sentiment === 'neutral' ? 'Neutral — keep engaged' : 'Negative — address concerns') }}
                    </span>
                </div>
                <div class="score-row">
                    <span class="score-label">Readiness</span>
                    <span class="score-value {{ $readiness['class'] }}">{{ $readiness['score'] }}</span>
                </div>
                <div class="score-row">
                    <span class="score-label">Follow-ups captured</span>
                    <span class="score-value">{{ $totalLines }}{{ $totalLines === 1 ? ' line' : ' lines' }}</span>
                </div>
            </div>

            <div class="rview-panel">
                <div class="rview-label">Talk split</div>
                <div class="score-row">
                    <span class="score-label">You</span>
                    <span class="score-value">{{ $agentPct }}%</span>
                </div>
                <div class="score-row">
                    <span class="score-label">Customer</span>
                    <span class="score-value">{{ $customerPct }}%</span>
                </div>
                <div class="tail-bar">
                    <div class="tail-agent" style="width: {{ $agentPct }}%;"></div>
                    <div class="tail-cust" style="width: {{ $customerPct }}%;"></div>
                </div>
            </div>

            <div class="rview-panel">
                <div class="rview-label">AI original briefing</div>
                <div class="ai-original"><span>DeAlly wrote:</span> "{!! e($call->summary ?? 'No summary generated.') !!}"</div>
            </div>

            @if ($gaps->isNotEmpty())
                <div class="rview-panel">
                    <div class="rview-label">Objections &amp; gaps logged</div>
                    <div class="obj-log">
                        @foreach ($gaps as $gap)
                            <div class="obj-log-item">
                                <span>{{ $gap->text }}</span>
                                <span class="mono" style="text-transform: capitalize;">{{ $gap->type }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($call->notes)
                <div class="rview-panel">
                    <div class="rview-label">Your notes</div>
                    <div class="brief-body">"{{ $call->notes }}"</div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection