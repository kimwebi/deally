@extends('core::layouts.call', ['title' => $call->name.' · Live'])

@php
    use Deally\Calls\Models\Call;
@endphp

@section('content')
<div class="lc-shell">
    <header class="lc-topbar">
        <div class="lc-title">
            <span class="lc-company">{{ $call->company }}</span>
            <span class="lc-timer mono" id="call-timer" data-start="{{ $call->date->timestamp }}">00:00</span>
        </div>
        <div class="lc-topbar-center">
            <span class="live-pill"><span class="live-dot"></span>Ready</span>
        </div>
        <div class="lc-topbar-right">
            <div class="source-statuses" role="status">
                <span class="source-status"><span class="source-name">You</span> <span class="source-state" id="source-agent-status">○ Off</span></span>
                <span class="source-status"><span class="source-name">Meeting</span> <span class="source-state" id="source-customer-status">○ Off</span></span>
            </div>
            <button class="mic-toggle" id="live-mic-toggle"
                data-transcribe-url="{{ route('deally.calls.live.transcribe', $call) }}"
                data-start-url="{{ route('deally.calls.live.start', $call) }}"
                data-query-url="{{ route('deally.calls.live.query', $call) }}"
                data-feedback-url="{{ route('deally.calls.live.finding.feedback', ['call' => $call, 'finding' => '__ID__']) }}"
                data-demo-mode="{{ $demoMode ? 'true' : 'false' }}"><i class="bi bi-mic-fill"></i> Start</button>
            <form id="end-call-form" class="end-call-wrap" method="POST" action="{{ route('deally.calls.end', $call) }}">
                @csrf
                <input type="hidden" name="duration" id="end-duration">
                <input type="hidden" name="notes" id="end-notes">
                <input type="hidden" name="sentiment" value="">
                <button type="submit" class="end-btn" data-end-call><i class="bi bi-telephone-x-fill"></i> End Call</button>
            </form>
        </div>
    </header>

    <div class="call-note">
        <span class="call-note-provider">Transcription &amp; suggestions: <strong>{{ strtoupper($assistantName) }}</strong></span>
        {{-- The platform's real state, including when it is not connected.
             Silence here would let a rep believe a bot is in the meeting. --}}
       {{-- <span class="call-note-platform {{ $call->bot_join_status === Call::BOT_JOIN_JOINED ? 'is-joined' : 'is-unconfirmed' }}">
            <i class="bi bi-robot" style="color: rebeccapurple"></i> {{ $platform }}
        </span>
        <span class="call-note-actions">
            <button type="button" class="link-btn" id="invite-preview-btn"
                data-preview-url="{{ route('deally.calls.invite.preview', $call) }}">Preview invitation</button>
            <button type="button" class="link-btn" id="bot-join-btn"
                data-join-url="{{ route('deally.calls.join', $call) }}">Admit bot</button>
        </span>--}}
    </div>

    <div class="lc-body">
        {{-- Left rail — customer card --}}
        <aside class="call-rail-left lc-customer">
            <div class="customer-card">
                <div class="customer-name-big">{{ $brief->contactName }}</div>
                <div class="customer-role-sm">{{ $brief->contactRole ?: 'Role not recorded' }}</div>
                <div class="customer-company-sm"><i class="bi bi-building"></i> {{ $brief->company }}</div>

                @if ($brief->dealStage)
                    <div class="customer-company-sm"><i class="bi bi-bar-chart-fill"></i> {{ ucfirst($brief->dealStage) }}
                        @if ($brief->dealValue)
                            · {{ number_format($brief->dealValue, 0) }}
                        @endif
                    </div>
                @endif

                <div class="customer-divider"></div>

                {{-- Every section states when it has nothing. An invented
                     signal is worse than a blank one: a rep cannot tell fiction
                     from fact while a customer is waiting. --}}
                <div class="rail-subhead">Signals</div>
                @if ($brief->sentimentTrend)
                    <div class="rail-item"><i class="bi bi-graph-up"></i> Sentiment {{ $brief->sentimentTrend }}</div>
                @endif

                @forelse ($brief->commitments() as $commitment)
                    <div class="rail-item"><i class="bi bi-pin-angle-fill" style="color: #540101"></i> {{ $commitment['text'] }}</div>
                @empty
                    @if (! $brief->sentimentTrend)
                        <div class="rail-item rail-item-quiet">No tracked signals for this account yet.</div>
                    @else
                        <div class="rail-item rail-item-quiet">Nothing outstanding from earlier calls.</div>
                    @endif
                @endforelse

                <div class="customer-divider"></div>

                <div class="rail-subhead">Earlier calls</div>
                @forelse ($brief->history() as $entry)
                    <div class="rail-item">
                        <a href="{{ $entry['url'] }}"><i class="bi bi-chat-dots-fill"></i> {{ $entry['date'] }} · {{ ucfirst($entry['sentiment']) }}</a>
                    </div>
                @empty
                    <div class="rail-item rail-item-quiet">This is the first recorded call with {{ $brief->company }}.</div>
                @endforelse

                @if ($brief->proposedPackages !== [])
                    <div class="customer-divider"></div>

                    <div class="rail-subhead">On the table</div>
                    @foreach ($brief->proposedPackages as $package)
                        <div class="rail-stack-item highlight"><span>⚡</span><div><div class="rail-stack-name">{{ $package }}</div><div class="rail-stack-meta">Proposed on this deal</div></div></div>
                    @endforeach
                @endif
            </div>

            <div class="tasks-mini">
                <div class="rail-subhead">Tasks due</div>
                @forelse($tasks as $task)
                    <div class="task-due {{ $task->status === 'closed' ? 'closed' : ($task->due_at?->isPast() ? 'overdue' : '') }}">
                        <span>{{ $task->title }}</span>
                        <span class="mono">{{ $task->due_at?->format('M d, g:ia') ?: '—' }}</span>
                    </div>
                @empty
                    <div class="rail-item rail-item-quiet">Nothing due.</div>
                @endforelse
            </div>
        </aside>

        {{-- Centre — ephemeral stream + hero + chat dock --}}
        <section class="call-centre-wrap lc-ai">
            <div class="ephemeral-stream ephemeral" id="stream">
                <div class="ephemeral-idle" id="stream-idle">
                    <div class="ephemeral-idle-icon"><i class="bi bi-stars"></i></div>
                    <div class="ephemeral-idle-text">DeAlly is listening</div>
                    <div class="ephemeral-idle-sub">Findings appear on the right as the call develops</div>
                </div>
            </div>

            <div class="hero" id="hero">
                <div class="hero-card">
                    <div class="hero-eyebrow" id="hero-eyebrow">
                        <span class="hero-eyebrow-icon"><i class="bi bi-stars"></i></span>
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
                        <span class="chat-input-icon"><i class="bi bi-stars"></i></span>
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

            <div class="kb-shelf" id="findings" data-shelf-limit="{{ $findingsLimit }}">
                <div class="kb-empty" id="kb-empty">Listening…<br>Findings appear a few seconds after something is said, and you can say the customer's part yourself to try it out.</div>
            </div>
            <div class="kb-shelf-note mono" id="kb-shelf-note"
                 data-total="{{ $findingsTotal }}"
                 data-limit="{{ $findingsLimit }}"
                 @if ($findingsTotal <= $findingsLimit) hidden @endif>Newest {{ $findingsLimit }} shown · older findings stay saved</div>
            <script type="application/json" id="initial-findings">@json($findings->map(fn ($finding) => $finding->toCard())->values())</script>

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
