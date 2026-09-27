@extends('core::layouts.call', ['title' => 'Review · '.$call->company])

@section('content')
@php
    $sentiment = $call->sentiment ?: 'neutral';
    $readiness = $sentiment === 'positive'
        ? ['score' => 'Very ready', 'class' => 'good', 'chip' => 'hot']
        : ($sentiment === 'neutral' ? ['score' => 'Somewhat ready', 'class' => 'warn', 'chip' => 'warm'] : ['score' => 'Needs work', 'class' => 'warn', 'chip' => 'cold']);

    $customerLines = $call->transcriptLines->where('is_agent', false)->count();
    $totalLines = max(1, $call->transcriptLines->count());
    $customerPct = round($customerLines / $totalLines * 100);
    $agentPct = 100 - $customerPct;

    $objections = $gaps->whereIn('type', ['objection', 'gap']);
    $objectionTotal = $objections->count();
    $objectionHandled = $objections->where('status', 'live')->count();
    $corrections = $gaps->where('type', 'correction');

    /* Windows are keyed by chunk id so a transcript line can point at the exact
       audio it came from. Interleaved playback is the whole point of keeping
       them: a rep reviewing a line should not have to hunt for it by ear. */
    $windowByChunk = $recordings->values()->mapWithKeys(
        fn ($recording, $i): array => [$recording->client_chunk_id => $i]
    );
    $replayIndex = $recordings->values()->map(fn ($recording, $i): array => [
        'id' => $i,
        'source' => $recording->source,
        'ms' => (int) ($recording->duration_ms ?: 0),
        'url' => route('deally.calls.recording', ['call' => $call, 'recording' => $recording->id]),
    ])->values();

    $recordedSeconds = (int) round($replayIndex->sum('ms') / 1000);
    $recordedClock = sprintf('%d:%02d', intdiv($recordedSeconds, 60), $recordedSeconds % 60);
    $helpful = $findings->where('status', 'helpful')->count();
    $unhelpful = $findings->where('status', 'unhelpful')->count();
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

    @if ($recordings->isNotEmpty())
        <div class="review-replay" id="call-replay">
            <button class="replay-toggle" id="replay-toggle" type="button" aria-label="Play the call recording">▶</button>
            <div class="replay-track" id="replay-track" role="slider" aria-label="Seek within the recording" tabindex="0">
                <span class="replay-thumb"></span>
            </div>
            <span class="replay-time mono" id="replay-elapsed">00:00 / {{ $recordedClock }}</span>
            <div class="replay-sources">
                <button class="replay-source selected" type="button" data-source="all" aria-pressed="true">Both</button>
                <button class="replay-source" type="button" data-source="agent" aria-pressed="false">You</button>
                <button class="replay-source" type="button" data-source="customer" aria-pressed="false">Meeting</button>
            </div>
            <span class="replay-note" id="replay-note">{{ $recordings->count() }} windows · {{ $recordedClock }} captured</span>
            <script type="application/json" id="replay-index">@json($replayIndex)</script>
        </div>
    @else
        <div class="review-replay is-absent">
            No audio was kept for this call. Replay is available for calls started after recording was enabled.
        </div>
    @endif

    <div class="review-body">
        {{-- Transcript, with what the assistant said at each turn --}}
        <div class="review-transcript">
            <div class="section-label"><span class="lbl-left"><span class="dot"></span>Conversation</span><span class="section-count">{{ $totalLines }} lines</span></div>

            @forelse ($call->transcriptLines as $line)
                @php
                    $lineFindings = $findingsByLine->get($line->id, collect());
                    $window = $line->client_chunk_id ? $windowByChunk->get($line->client_chunk_id) : null;
                @endphp
                <div class="transcript-line" @if ($window !== null) data-replay-window="{{ $window }}" @endif>
                    <div class="speaker {{ $line->is_agent ? 'agent' : '' }}">
                        {{ $line->is_agent ? 'Agent' : $line->speaker }}
                        @if ($window !== null)
                            <button class="transcript-play" type="button" title="Play from this moment">▶</button>
                        @endif
                    </div>
                    <div class="transcript-text">{{ $line->text }}</div>
                </div>
                @if ($line->linked_text)
                    <div class="transcript-linked {{ $line->linked_type === 'competitor' ? 'competitor' : '' }}">
                        {{ $line->linked_type === 'competitor' ? '🔵 Battle card shown · ' : '⚪ ' }}{{ $line->linked_text }}
                    </div>
                @endif
                @if ($lineFindings->isNotEmpty())
                    <div class="review-ai-turn">
                        <div class="review-ai-turn-label">✦ DeAlly said</div>
                        <div class="review-ai-cards">
                            @foreach ($lineFindings as $card)
                                @php($cardData = $card->toCard())
                                <div class="kb-card {{ $cardData['role'] }}">
                                    <span class="kb-accent"></span>
                                    <div class="kb-top">
                                        <span class="kb-tag">{{ $cardData['label'] ?: $cardData['kind'] }}</span>
                                        @if ($cardData['confidence'])
                                            <span class="kb-conf">{{ $cardData['confidence'] }}</span>
                                        @endif
                                    </div>
                                    <div class="kb-body">{{ $cardData['body'] }}</div>
                                    <div class="kb-foot">
                                        @if ($cardData['package'])<div class="kb-foot-primary">{{ $cardData['package'] }}</div>@endif
                                        @if ($cardData['status'] !== 'new')
                                            <div class="kb-foot-source">Marked {{ $cardData['status'] }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
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
                    <div class="score-row"><span class="score-label">AI suggestions</span><span class="score-value">{{ $findings->count() }} cards</span></div>
                    <div class="score-row"><span class="score-label">Marked useful</span><span class="score-value {{ $helpful > 0 ? 'good' : '' }}">{{ $helpful }}{{ $unhelpful > 0 ? ' · '.$unhelpful.' not' : '' }}</span></div>
                    <div class="score-row"><span class="score-label">Overall</span><span class="score-value">{{ $readiness['score'] }}</span></div>
                </div>
            </div>

            <div class="panel-section">
                <div class="panel-section-label"><span>You asked DeAlly</span><span style="font-size:10px;color:var(--text-4)">{{ $queries->count() }}</span></div>
                @forelse ($queries as $query)
                    <div class="review-query">
                        <div class="review-query-prompt">{{ $query->prompt }}</div>
                        <div class="review-query-answer">{{ $query->answer ?: '— no answer recorded —' }}</div>
                        @if ($query->cards)
                            <div class="review-query-source">{{ collect($query->cards)->pluck('body')->filter()->take(2)->implode(' · ') }}</div>
                        @endif
                    </div>
                @empty
                    <div class="brief-body">You did not ask the assistant anything during this call.</div>
                @endforelse
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