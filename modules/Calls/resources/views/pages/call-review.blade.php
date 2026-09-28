@extends('core::layouts.call', ['title' => 'Review · '.$call->company])

@section('content')
@php
    $brief = app(\Deally\Calls\Services\CallReviewBrief::class)->forCalls([$call->id])->get($call->id);
    $reads = $brief->reads();

    $customerLines = $call->transcriptLines->where('is_agent', false)->count();
    $totalLines = max(1, $call->transcriptLines->count());
    $customerPct = round($customerLines / $totalLines * 100);
    $agentPct = 100 - $customerPct;

    $objectionLog = $brief->objectionLog();
    $objectionTotal = count($objectionLog);
    $objectionHandled = $gaps->whereIn('type', ['objection', 'gap'])->where('status', 'live')->count();
    $corrections = $brief->correctionLog();

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
            {{-- Both reads are correctable, and the AI's original is kept and
                 shown. A read the rep cannot change is decoration; a read they
                 can change but which erases the model's own answer throws away
                 the only comparison worth having. --}}
            <div class="panel-section">
                <div class="panel-section-label"><span>Sentiment</span></div>
                <div class="panel-chip-row" data-correct-group="sentiment"
                    data-correct-url="{{ route('deally.calls.correct', $call) }}">
                    @foreach (['positive' => '😊 Positive', 'neutral' => '😐 Neutral', 'negative' => '😞 Negative'] as $value => $label)
                        <span class="panel-chip {{ $reads['sentiment'] === $value ? 'selected '.($value === 'positive' ? 'positive' : ($value === 'neutral' ? 'neutral' : 'negative')) : '' }}"
                            data-correct-value="{{ $value }}" role="button" tabindex="0">{{ $label }}</span>
                    @endforeach
                </div>
                <div class="ai-read-note">
                    AI read: {{ ucfirst($reads['ai_sentiment'] ?? 'neutral') }}
                    @if ($reads['sentiment_corrected'])
                        · <span class="ai-read-corrected">corrected by you</span>
                    @endif
                </div>
            </div>

            <div class="panel-section">
                <div class="panel-section-label"><span>Close-Readiness</span></div>
                <div class="panel-chip-row" data-correct-group="readiness"
                    data-correct-url="{{ route('deally.calls.correct', $call) }}">
                    @foreach (['hot' => '🔥 Hot', 'warm' => '🌤 Warm', 'cold' => '❄ Cold'] as $value => $label)
                        <span class="panel-chip {{ $reads['readiness'] === $value ? 'selected '.$value : '' }}"
                            data-correct-value="{{ $value }}" role="button" tabindex="0">{{ $label }}</span>
                    @endforeach
                </div>
                <div class="ai-read-note">
                    AI read: {{ ucfirst($reads['ai_readiness'] ?? 'warm') }}
                    @if ($reads['readiness_corrected'])
                        · <span class="ai-read-corrected">corrected by you</span>
                    @endif
                </div>
            </div>

            <div class="panel-section">
                <div class="panel-section-label"><span>Deal status</span><span style="font-size:10px;color:var(--text-4)">{{ $brief->openFlags->count() }} open</span></div>

                @forelse ($brief->openFlags as $flag)
                    <div class="review-flag" data-flag-id="{{ $flag->id }}">
                        <div class="review-flag-head">
                            <strong>{{ $flag->headline }}</strong>
                            <span class="status-pill rejected">Unresolved</span>
                        </div>
                        @if ($flag->rationale)
                            <p class="review-flag-why">{{ $flag->rationale }}</p>
                        @endif
                        <div class="review-flag-actions">
                            @foreach (['confirmed' => 'Risk is real — deal updated', 'adjusted' => 'Adjusted', 'dismissed' => 'Not a real risk'] as $status => $label)
                                <button type="button" class="btn-sm" data-resolve-flag="{{ $status }}"
                                    data-flag-url="{{ route('deally.calls.flags.resolve', ['call' => $call, 'flag' => $flag->id]) }}">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="brief-body">No deal-status flags on this call.</div>
                @endforelse

                <div class="review-add-flag">
                    <input class="input-field" type="text" id="review-flag-input" placeholder="Raise a deal-status flag…">
                    <input class="input-field" type="text" id="review-flag-why" placeholder="Why (optional)">
                    <button type="button" class="btn-sm" id="review-flag-add"
                        data-flag-url="{{ route('deally.calls.flags.store', $call) }}">Raise</button>
                </div>
            </div>

            <div class="panel-section">
                <div class="panel-section-label"><span>Agent Performance</span></div>
                <div class="score-card">
                    <div class="score-row"><span class="score-label">Talk ratio</span><span class="score-value {{ $agentPct >= 50 ? 'warn' : 'good' }}">{{ $agentPct }}% agent</span></div>
                    <div class="talk-bar"><div class="talk-bar-agent" style="flex: {{ $agentPct }};"></div><div class="talk-bar-customer" style="flex: {{ $customerPct }};"></div></div>
                    <div class="score-row"><span class="score-label">Objections handled</span><span class="score-value {{ $objectionHandled >= $objectionTotal && $objectionTotal > 0 ? 'good' : 'warn' }}">{{ $objectionTotal > 0 ? $objectionHandled.' of '.$objectionTotal : 'None logged' }}</span></div>
                    <div class="score-row"><span class="score-label">AI suggestions</span><span class="score-value">{{ $findings->count() }} cards</span></div>
                    <div class="score-row"><span class="score-label">Marked useful</span><span class="score-value {{ $helpful > 0 ? 'good' : '' }}">{{ $helpful }}{{ $unhelpful > 0 ? ' · '.$unhelpful.' not' : '' }}</span></div>
                    <div class="score-row"><span class="score-label">Overall</span><span class="score-value">{{ ucfirst($reads['readiness']) }}</span></div>
                </div>
            </div>

            <div class="panel-section">
                <div class="panel-section-label"><span>What DeAlly heard</span><span style="font-size:10px;color:var(--text-4)">{{ $heard->count() }}</span></div>
                @forelse ($heard as $line)
                    <div class="review-heard-item">
                        @if ($line->created_at)
                            <span class="review-heard-at">{{ $line->created_at->format('H:i:s') }}</span>
                        @endif
                        <span>{{ $line->body }}</span>
                    </div>
                @empty
                    <div class="brief-body">
                        Nothing was paraphrased on this call. The assistant only writes a "heard" line
                        when the conversation said something that changed the read.
                    </div>
                @endforelse
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
                @forelse ($objectionLog as $objection)
                    <div class="objection-item">
                        <span>
                            @if ($objection['added_by_rep'])
                                <span class="objection-origin">You added</span>
                            @endif
                            {{ $objection['text'] }}
                        </span>
                    </div>
                @empty
                    <div class="brief-body">No objections or gaps logged.</div>
                @endforelse

                {{-- An agent hearing resistance the model missed is the most
                     valuable correction available, so it can be added here
                     rather than only being raised live. --}}
                <div class="review-add-objection">
                    <input class="input-field" type="text" id="review-objection-input"
                        placeholder="An objection DeAlly missed…">
                    <button type="button" class="btn-sm" id="review-objection-add"
                        data-objection-url="{{ route('deally.calls.objections.store', $call) }}">Add</button>
                </div>
            </div>

            <div class="panel-section">
                <div class="panel-section-label"><span>Corrections</span></div>
                <div style="display:flex;flex-direction:column;gap:8px">
                    @forelse ($corrections as $correction)
                        <div style="background:var(--surface);border:1px solid var(--border-soft);border-radius:var(--r-md);padding:10px 12px;font-size:12px;display:flex;justify-content:space-between;align-items:center;gap:10px">
                            <span style="color:var(--text-3)">
                                <strong style="text-transform:capitalize">{{ str_replace('_', ' ', $correction['field']) }}</strong>:
                                {{ $correction['ai_value'] }} → <strong>{{ $correction['corrected_value'] }}</strong>
                                @if ($correction['note'])
                                    <em style="color:var(--text-4)"> — {{ $correction['note'] }}</em>
                                @endif
                            </span>
                            <span style="color:var(--text-4);white-space:nowrap">{{ $correction['at'] }}</span>
                        </div>
                    @empty
                        <div class="brief-body">No corrections this call. Correct sentiment or readiness above and it is recorded here.</div>
                    @endforelse
                </div>
            </div>

            <div class="panel-section">
                @if ($call->proposal_intent)
                    {{-- Only when the call actually agreed one. An unconditional
                         button on every call is a suggestion the rep has to
                         evaluate and discard. --}}
                    <a class="btn-proposal" href="{{ route('deally.proposals.index') }}">📄 Create Proposal</a>
                    <div style="font-size:11px;color:var(--text-4);text-align:center">
                        Agreed on this call{{ $call->proposal_intent_note ? ': '.$call->proposal_intent_note : '' }}
                    </div>
                @else
                    <div class="brief-body" style="text-align:center">
                        No proposal was agreed on this call, so none is offered.
                        @if ($brief->gaps->where('type', 'proposal')->isNotEmpty())
                            Pricing or scope came up — see the gaps above.
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection