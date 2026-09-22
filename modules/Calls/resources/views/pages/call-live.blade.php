@extends('core::layouts.call', ['title' => $call->name.' · Live'])

@section('content')
<div class="lc-shell">
    <header class="lc-topbar">
        <div class="lc-title">
            <span class="lc-company">{{ $call->company }}</span>
            <span class="lc-timer mono" id="call-timer" data-start="{{ $call->date->timestamp }}">00:00</span>
        </div>
        <div class="lc-topbar-right">
            <button class="mic-toggle" id="live-mic-toggle"
                data-transcribe-url="{{ route('deally.calls.live.transcribe', $call) }}"
                data-query-url="{{ route('deally.calls.live.query', $call) }}"
                data-flag-url="{{ route('deally.calls.flag', $call) }}"><i class="bi bi-mic-fill"></i> Mic</button>
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
        {{-- Left rail — customer card --}}
        <aside class="call-rail-left lc-customer">
            <div class="customer-card">
                <div class="customer-name-big">{{ $call->contact_name ?: 'Customer' }}</div>
                <div class="customer-role-sm">{{ $call->contact_role ?: 'Prospect' }}</div>
                <div class="customer-company-sm">🏢 {{ $call->company }}</div>

                <div class="customer-divider"></div>

                <div class="rail-subhead">Signals</div>
                <div class="rail-item">📈 Sentiment trending positive over last 3 calls</div>
                <div class="rail-item">⚠️ Budget mentioned 4 times — price sensitivity</div>
                <div class="rail-item">📌 Unresolved: spec sheet promised, not sent</div>

                <div class="customer-divider"></div>

                <div class="rail-subhead">Last call</div>
                <div class="rail-item">💬 Compared us on support speed; strong interest in a 2-week pilot.</div>

                <div class="customer-divider"></div>

                <div class="rail-subhead">Current stack</div>
                <div class="rail-stack-item"><span>🏢</span><div><div class="rail-stack-name">Legacy tool</div><div class="rail-stack-meta">Support pain</div></div></div>
                <div class="rail-stack-item highlight"><span>⚡</span><div><div class="rail-stack-name">Enterprise Suite</div><div class="rail-stack-meta">3 subs proposed</div></div></div>
                <div class="rail-stack-item"><span>📦</span><div><div class="rail-stack-name">Slack</div><div class="rail-stack-meta">Native integration</div></div></div>
            </div>

            <div class="tasks-mini">
                <div class="rail-subhead">Tasks due</div>
                @forelse($tasks as $task)
                    <div class="task-due {{ $task->status === 'closed' ? 'closed' : ($task->due_at?->isPast() ? 'overdue' : '') }}">
                        <span>{{ $task->title }}</span>
                        <span class="mono">{{ $task->due_at?->format('M d, g:ia') ?: '—' }}</span>
                    </div>
                @empty
                    <div class="rail-item">Nothing due.</div>
                @endforelse
            </div>
        </aside>

        {{-- Centre — ephemeral stream + hero + chat dock --}}
        <section class="call-centre-wrap lc-ai">
            <div class="ephemeral-stream ephemeral" id="stream">
                <div class="ephemeral-idle" id="stream-idle">
                    <div class="ephemeral-idle-icon">✦</div>
                    <div class="ephemeral-idle-text">DeAlly is listening</div>
                    <div class="ephemeral-idle-sub">Findings appear on the right as the call develops</div>
                </div>
            </div>

            <div class="hero" id="hero">
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
                <div class="recent-strip" id="dot-strip"></div>
                <div class="chat-row">
                    <button class="obj-btn" id="obj-btn" title="Flag objection"><i class="bi bi-flag-fill"></i></button>
                    <input id="obj-input" class="obj-input" placeholder="What did they push back on?" hidden>
                    <div class="chat-input-inner">
                        <span class="chat-input-icon">✦</span>
                        <input id="query-input" class="chat-input" placeholder="Ask DeAlly — pricing, comparison, feature check…">
                        <button class="chat-send" id="query-send">→</button>
                    </div>
                </div>
                <div class="keyboard-hint mono">Shortcuts: <kbd>/</kbd> ask · <kbd>N</kbd> notes · <kbd>?</kbd> keys · <kbd>⌘</kbd>+<kbd>Enter</kbd> end</div>
            </div>
        </section>

        {{-- Right rail — KB findings + notes --}}
        <aside class="call-rail-right lc-findings">
            <div class="rail-right-head">
                <div class="rail-right-title"><span class="rail-dot"></span>Findings</div>
                <span class="rail-right-count mono" id="findings-count">00</span>
            </div>

            <div class="kb-shelf" id="findings">
                <div class="kb-empty" id="kb-empty">Waiting for your first suggestion…<br>The AI surfaces say / ask / reference cards here.</div>
            </div>

            <div class="notes-pinned">
                <div class="notes-area">
                    <div class="notes-label">Notes <span class="mono area-key">N</span></div>
                    <textarea id="notes-input" class="notes-textarea" placeholder="Type throughout the call. Saved with the call.">{{ $call->notes }}</textarea>
                </div>
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