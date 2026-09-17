@extends('core::layouts.call', ['title' => $call->name.' · Live'])

@section('content')
<div class="call-shell">
    <div class="call-header">
        <div class="live-pill"><span class="live-dot"></span>Listening</div>
        <div class="call-company">{{ $call->name }} · {{ $call->company }}</div>
        <div class="call-timer" id="call-timer" data-start="{{ $call->date->timestamp }}">00:00</div>
        <button class="mic-toggle" id="live-mic-toggle"
            data-transcribe-url="{{ route('deally.calls.live.transcribe', $call) }}"
            data-query-url="{{ route('deally.calls.live.query', $call) }}">🎤 Start Listening</button>
        <a href="{{ route('deally.calls.summary', $call) }}" class="end-btn" data-end-call>⬤ End</a>
    </div>
    <div class="call-note">🔒 Recorded for transparency — the customer has been informed this call is recorded.</div>

    <div class="call-body">
        <div class="call-panel call-left">
            <div class="customer-profile">
                <div class="customer-name">{{ $call->contact_name ?: 'Customer' }}</div>
                <div class="customer-role">{{ $call->contact_role ?: 'Prospect' }}</div>
                <div class="customer-company">{{ $call->company }}</div>
            </div>
            <div class="brief-card">
                <div class="brief-header">Pre-Call Brief</div>
                <div class="brief-item"><span>📈</span><span>Sentiment trending positive over last 3 calls</span></div>
                <div class="brief-item"><span>⚠️</span><span>Budget mentioned 4 times — price sensitivity</span></div>
                <div class="brief-item"><span>📌</span><span>Unresolved: spec sheet promised, not sent</span></div>
            </div>
            <div class="notes-area">
                <div class="notes-label">Your Notes</div>
                {{ $call->notes ?: 'Add your own notes here during the call.' }}
            </div>
            <button class="action-btn">🔔 Get Expert Help</button>
            <button class="action-btn">🚩 Flag Objection</button>
        </div>

        <div class="call-panel call-centre">
            <div class="reasoning-header">
                <span class="lbl"><span class="ai-spark">✦</span>AI Reasoning</span>
                <span class="reasoning-header-right">
                    <span class="scenario-badge" id="scenario-badge">Warming up…</span>
                    <span class="cap-indicator">Last 3</span>
                </span>
            </div>
            <div class="stream" id="stream"></div>
            <div class="query-box">
                <input id="query-input" placeholder="Ask DeAlly anything during the call…">
                <button class="query-send">→</button>
            </div>
        </div>

        <div class="call-panel call-right">
            <div class="suggestions-header"><span>Results Shelf</span><span class="sugg-count" id="sugg-count">0</span></div>
            <div class="suggestions" id="suggestions"></div>
        </div>
    </div>
</div>

<script id="live-script" type="application/json">@json($script)</script>
@endsection