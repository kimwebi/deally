@extends('core::layouts.call', ['title' => $call->name.' · Live'])

@section('content')
<div class="lc-shell">
    <header class="lc-topbar">
        <div class="lc-title">
            <span class="lc-company">{{ $call->company }}</span>
            <span class="lc-timer mono" id="call-timer" data-start="{{ $call->date->timestamp }}">00:00</span>
        </div>
        <div class="lc-topbar-center">
            <span class="live-pill" id="live-pill"><span class="live-dot"></span>DeAlly is listening</span>
        </div>
        <div class="lc-topbar-right">
            <button class="mic-toggle" id="live-mic-toggle"
                data-transcribe-url="{{ route('deally.calls.live.transcribe', $call) }}"
                data-query-url="{{ route('deally.calls.live.query', $call) }}"
                data-flag-url="{{ route('deally.calls.flag', $call) }}"><i class="bi bi-mic-fill"></i> Start</button>
            <form id="end-call-form" class="end-call-wrap" method="POST" action="{{ route('deally.calls.end', $call) }}">
                @csrf
                <input type="hidden" name="duration" id="end-duration">
                <input type="hidden" name="notes" id="end-notes">
                <input type="hidden" name="sentiment" value="">
                <button type="submit" class="end-btn" data-end-call><i class="bi bi-telephone-x-fill"></i> End Call</button>
            </form>
        </div>
    </header>

    <div class="call-note">🔒 Recorded for transparency — the customer has been informed this call is recorded.</div>

    <div class="lc-body">
        {{-- Customer Panel — read-only brief --}}
        <aside class="lc-panel lc-customer">
            <div class="lc-panel-head">
                <span class="lc-panel-title">Customer brief</span>
                <span class="lc-lock">Fixed during call</span>
            </div>

            <div class="customer-profile">
                <div class="customer-name">{{ $call->contact_name ?: 'Customer' }}</div>
                <div class="customer-role">{{ $call->contact_role ?: 'Prospect' }} · {{ $call->company }}</div>
            </div>

            <div class="brief-section">
                <div class="brief-label">Signals</div>
                <div class="brief-item">📈 Sentiment trending positive over last 3 calls</div>
                <div class="brief-item">⚠️ Budget mentioned 4 times — price sensitivity</div>
                <div class="brief-item">📌 Unresolved: spec sheet promised, not sent</div>
            </div>

            <div class="brief-section">
                <div class="brief-label">Last call</div>
                <div class="brief-body">Compared us on support speed; strong interest in a 2-week pilot.</div>
            </div>

            <div class="brief-section">
                <div class="brief-label">Current stack</div>
                <div class="brief-body">Legacy tool (support pain) · evaluating Enterprise Suite + native Slack.</div>
            </div>

            <div class="brief-section">
                <div class="brief-label">Tasks due</div>
                @forelse($tasks as $task)
                    <div class="task-due {{ $task->status === 'closed' ? 'closed' : ($task->due_at?->isPast() ? 'overdue' : '') }}">
                        <span>{{ $task->title }}</span>
                        <span class="mono">{{ $task->due_at?->format('M d, g:ia') ?: '—' }}</span>
                    </div>
                @empty
                    <div class="brief-body">Nothing due.</div>
                @endforelse
            </div>
        </aside>

        {{-- AI Panel — ephemeral stream + hero + chat dock --}}
        <section class="lc-panel lc-ai">
            <div class="ephemeral" id="stream">
                <div class="ephem-idle" id="stream-idle">DeAlly is listening.</div>
            </div>

            <div class="hero" id="hero" hidden>
                <div class="hero-card">
                    <div class="hero-eyebrow" id="hero-eyebrow">
                        <span class="hero-eyebrow-icon">✦</span>
                        <span class="hero-eyebrow-label">AI Asks You</span>
                    </div>
                    <div class="hero-question" id="hero-question"></div>
                    <div class="hero-chips" id="hero-chips"></div>
                    <button class="hero-action" id="hero-expert" hidden><i class="bi bi-bell-fill"></i> Request Instant Expert Help</button>
                    <div class="hero-context" id="hero-context"></div>
                </div>
            </div>

            <div class="chat-dock">
                <div class="dot-strip" id="dot-strip"></div>
                <div class="chat-row">
                    <button class="obj-btn" id="obj-btn" title="Flag objection"><i class="bi bi-flag-fill"></i></button>
                    <input id="obj-input" class="obj-input" placeholder="What did they push back on?" hidden>
                    <input id="query-input" class="chat-input" placeholder="Ask DeAlly — pricing, comparison, feature check…">
                    <button class="chat-send" id="query-send">→</button>
                </div>
                <div class="chat-hint mono">Shortcuts: <kbd>/</kbd> ask · <kbd>N</kbd> notes · <kbd>?</kbd> keys · <kbd>⌘</kbd>+<kbd>Enter</kbd> end</div>
            </div>
        </section>

        {{-- Findings Panel — read-only KB feedback --}}
        <aside class="lc-panel lc-findings">
            <div class="lc-panel-head">
                <span class="lc-panel-title">Findings</span>
                <span class="findings-count mono" id="findings-count">0</span>
            </div>

            <div class="findings-list" id="findings"></div>

            <div class="notes-area">
                <div class="notes-label">Notes <span class="mono area-key">N</span></div>
                <textarea id="notes-input" class="notes-textarea" placeholder="Type throughout the call. Saved with the call.">{{ $call->notes }}</textarea>
            </div>
        </aside>
    </div>
</div>

<div class="shortcuts-overlay" id="shortcuts-overlay" hidden>
    <div class="shortcuts-card">
        <div class="shortcuts-head">Keyboard shortcuts</div>
        <div class="shortcuts-row"><kbd>/</kbd><span>Focus the chat prompt</span></div>
        <div class="shortcuts-row"><kbd>N</kbd><span>Focus the notes field</span></div>
        <div class="shortcuts-row"><kbd>E</kbd><span>Request expert help on the active hero</span></div>
        <div class="shortcuts-row"><kbd>?</kbd><span>Show this overlay</span></div>
        <div class="shortcuts-row"><kbd>Esc</kbd><span>Close any overlay</span></div>
        <div class="shortcuts-row"><kbd>⌘</kbd>+<kbd>Enter</kbd><span>End the call</span></div>
        <div class="shortcuts-footer"><button class="shortcuts-close" id="shortcuts-close">Close</button></div>
    </div>
</div>
@endsection