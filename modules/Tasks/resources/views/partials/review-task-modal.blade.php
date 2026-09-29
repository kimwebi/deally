{{--
    The Review Call Task.

    This is the review. The full conversation lives one click away, but the things
    a rep has to *act on* — correct the AI's read, add an objection it missed, see
    what is still outstanding, resolve a deal flag — all live here, because
    making them require opening the whole transcript first is how they get
    skipped.

    Rendered identically from the tasks list and from the post-call summary, so a
    rep sees the same thing whichever route they arrived by.
--}}
@php
    $reads = $brief?->reads() ?? [
        'sentiment' => 'neutral',
        'ai_sentiment' => null,
        'sentiment_corrected' => false,
        'readiness' => 'warm',
        'ai_readiness' => null,
        'readiness_corrected' => false,
    ];
    $openFlags = $brief?->openFlags ?? collect();
    $correctUrl = $brief !== null ? route('deally.calls.correct', $brief->call) : null;
    $flagUrl = $brief !== null ? route('deally.calls.flags.store', $brief->call) : null;
    $objectionUrl = $brief !== null ? route('deally.calls.objections.store', $brief->call) : null;
    $proposalUrl = $brief !== null ? route('deally.calls.proposal', $brief->call) : null;
@endphp

<div class="modal modal-review-task">
    <div class="modal-header">
        <div class="modal-header-icon task">📋</div>
        <div class="modal-header-body">
            <div class="modal-title">{{ $task->title }}</div>
            <div class="modal-subtitle">
                {{ $task->linked_company ?: 'Unlinked' }}
                · {{ $task->due_at ? 'due '.$task->due_at->format('j M, g:ia') : 'no due date' }}
                @if ($task->status === 'closed')
                    · closed
                @endif
            </div>
        </div>
        <button class="modal-close" data-close-modal>✕</button>
    </div>

    <div class="modal-body review-task-body">
        @if ($brief === null)
            {{-- No call attached. Said plainly rather than rendering an empty
                 review that looks like there was nothing to review. --}}
            <p class="review-task-empty">
                This task is not linked to a recorded call, so there is nothing to review here.
                It is still a normal reminder — close it when it is done.
            </p>
        @else
            @if (($blocked = $task->blockedReason()) !== null)
                <div class="review-task-blocked" role="alert">
                    <strong>This task cannot be closed yet.</strong>
                    <span>{{ $blocked }}</span>
                </div>
            @endif

            <div class="modal-section">
                <div class="modal-section-label"><span class="dot"></span>What DeAlly heard</div>

                @forelse ($brief->heard() as $heard)
                    <div class="review-task-heard">
                        @if ($heard['at'])
                            <span class="review-task-heard-at">{{ $heard['at'] }}</span>
                        @endif
                        <span>{{ $heard['text'] }}</span>
                    </div>
                @empty
                    <p class="review-task-empty">
                        Nothing was paraphrased on this call — the assistant only writes a "heard" line
                        when the conversation said something that changed the read.
                    </p>
                @endforelse
            </div>

            <div class="modal-section">
                <div class="modal-section-label"><span class="dot"></span>How DeAlly read it</div>

                <div class="review-task-reads">
                    <div class="review-task-read">
                        <label>Sentiment</label>
                        @if ($reads['sentiment_corrected'])
                            <div class="review-task-read-value">
                                {{ ucfirst($reads['sentiment']) }}
                                <span class="review-task-was">AI said {{ $reads['ai_sentiment'] }}</span>
                            </div>
                        @else
                            <div class="review-task-read-value">{{ ucfirst($reads['sentiment']) }}</div>
                        @endif
                        <select class="input-field" data-correct-field="sentiment" data-correct-url="{{ $correctUrl }}"
                            @disabled($task->status === 'closed')>
                            @foreach (['positive', 'neutral', 'negative'] as $option)
                                <option value="{{ $option }}" @selected($reads['sentiment'] === $option)>{{ ucfirst($option) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="review-task-read">
                        <label>Close readiness</label>
                        @if ($reads['readiness_corrected'])
                            <div class="review-task-read-value">
                                {{ ucfirst($reads['readiness']) }}
                                <span class="review-task-was">AI said {{ $reads['ai_readiness'] }}</span>
                            </div>
                        @else
                            <div class="review-task-read-value">{{ ucfirst($reads['readiness']) }}</div>
                        @endif
                        <select class="input-field" data-correct-field="readiness" data-correct-url="{{ $correctUrl }}"
                            @disabled($task->status === 'closed')>
                            @foreach (['hot', 'warm', 'cold'] as $option)
                                <option value="{{ $option }}" @selected($reads['readiness'] === $option)>{{ ucfirst($option) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <p class="review-task-hint">
                    Correcting keeps the AI's original read on the record, so the difference is available for training.
                </p>
            </div>

            <div class="modal-section">
                <div class="modal-section-label"><span class="dot"></span>Agent Performance</div>
                @php $perf = $brief->agentPerformance(); @endphp
                <div class="score-card">
                    <div class="score-row"><span class="score-label">Talk ratio</span><span class="score-value {{ $perf['agent_pct'] >= 50 ? 'warn' : 'good' }}">{{ $perf['agent_pct'] }}% agent</span></div>
                    <div class="talk-bar"><div class="talk-bar-agent" style="flex: {{ $perf['agent_pct'] }};"></div><div class="talk-bar-customer" style="flex: {{ $perf['customer_pct'] }};"></div></div>
                    <div class="score-row"><span class="score-label">Objections handled</span><span class="score-value {{ $perf['objection_handled'] >= $perf['objection_total'] && $perf['objection_total'] > 0 ? 'good' : 'warn' }}">{{ $perf['objection_total'] > 0 ? $perf['objection_handled'].' of '.$perf['objection_total'] : 'None logged' }}</span></div>
                    <div class="score-row"><span class="score-label">AI suggestions</span><span class="score-value">{{ $perf['ai_suggestions'] }} cards</span></div>
                    <div class="score-row"><span class="score-label">Marked useful</span><span class="score-value {{ $perf['helpful'] > 0 ? 'good' : '' }}">{{ $perf['helpful'] }}{{ $perf['unhelpful'] > 0 ? ' · '.$perf['unhelpful'].' not' : '' }}</span></div>
                    <div class="score-row"><span class="score-label">Overall</span><span class="score-value">{{ ucfirst($perf['readiness']) }}</span></div>
                </div>
                <p class="review-task-hint">
                    The same numbers the coaching review shows for any rep — measured on this call, not on a recorded self-report.
                </p>
            </div>

            @if ($openFlags->isNotEmpty() || $brief !== null)
                <div class="modal-section">
                    <div class="modal-section-label"><span class="dot"></span>Deal status</div>

                    @forelse ($openFlags as $flag)
                        <div class="review-task-flag" data-flag-id="{{ $flag->id }}">
                            <div class="review-task-flag-head">
                                <strong>{{ $flag->headline }}</strong>
                                <span class="status-pill rejected">Unresolved</span>
                            </div>
                            @if ($flag->rationale)
                                <p class="review-task-flag-why">{{ $flag->rationale }}</p>
                            @endif
                            <div class="review-task-flag-actions">
                                <button type="button" class="btn-sm" data-resolve-flag="confirmed"
                                    data-flag-url="{{ route('deally.calls.flags.resolve', ['call' => $brief->call, 'flag' => $flag->id]) }}">Risk is real — deal updated</button>
                                <button type="button" class="btn-sm" data-resolve-flag="adjusted"
                                    data-flag-url="{{ route('deally.calls.flags.resolve', ['call' => $brief->call, 'flag' => $flag->id]) }}">Adjusted</button>
                                <button type="button" class="btn-sm" data-resolve-flag="dismissed"
                                    data-flag-url="{{ route('deally.calls.flags.resolve', ['call' => $brief->call, 'flag' => $flag->id]) }}">Not a real risk</button>
                            </div>
                        </div>
                    @empty
                        <p class="review-task-empty">No deal-status flags on this call.</p>
                    @endforelse

                    <div class="review-task-add-flag">
                        <input class="input-field" type="text" placeholder="Raise a deal-status flag…"
                            id="review-task-flag-input">
                        <input class="input-field" type="text" placeholder="Why (optional)" id="review-task-flag-why">
                        <button type="button" class="btn-sm" id="review-task-flag-add" data-flag-url="{{ $flagUrl }}">Raise</button>
                    </div>
                </div>
            @endif

            <div class="modal-section">
                <div class="modal-section-label"><span class="dot"></span>Objections</div>

                @forelse ($brief->objectionLog() as $objection)
                    <div class="review-task-objection {{ $objection['added_by_rep'] ? 'is-rep' : '' }}">
                        <span class="review-task-objection-origin">{{ $objection['added_by_rep'] ? 'You added' : 'DeAlly detected' }}</span>
                        <span>{{ $objection['text'] }}</span>
                    </div>
                @empty
                    <p class="review-task-empty">No objections were raised on this call.</p>
                @endforelse

                <div class="review-task-add-objection">
                    <input class="input-field" type="text" placeholder="An objection DeAlly missed…"
                        id="review-task-objection-input" @disabled($task->status === 'closed')>
                    <button type="button" class="btn-sm" id="review-task-objection-add"
                        data-objection-url="{{ $objectionUrl }}" @disabled($task->status === 'closed')>Add</button>
                </div>
            </div>

            <div class="modal-section">
                <div class="modal-section-label"><span class="dot"></span>Still outstanding</div>

                @forelse ($brief->missedActions() as $action)
                    <div class="review-task-action">
                        <span class="review-task-action-type">{{ str_replace('_', ' ', $action['type']) }}</span>
                        <span>{{ $action['text'] }}</span>
                    </div>
                @empty
                    <p class="review-task-empty">Nothing from this call is still open.</p>
                @endforelse
            </div>

            @if ($brief->correctionLog() !== [])
                <div class="modal-section">
                    <div class="modal-section-label"><span class="dot"></span>Corrections made</div>
                    @foreach ($brief->correctionLog() as $correction)
                        <div class="review-task-correction">
                            <span class="review-task-correction-field">{{ str_replace('_', ' ', $correction['field']) }}</span>
                            <span>{{ $correction['ai_value'] }} → <strong>{{ $correction['corrected_value'] }}</strong></span>
                            @if ($correction['note'])
                                <span class="review-task-correction-note">{{ $correction['note'] }}</span>
                            @endif
                            <span class="review-task-correction-at">{{ $correction['at'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($brief->call->proposal_intent)
                <div class="modal-section">
                    <div class="modal-section-label"><span class="dot"></span>Next step</div>
                    <p class="review-task-proposal">
                        This call agreed a proposal
                        @if ($brief->call->proposal_intent_note)
                            — {{ $brief->call->proposal_intent_note }}
                        @endif
                        .
                        <button type="button" class="btn-sm" id="review-task-proposal-create"
                            data-proposal-url="{{ $proposalUrl }}">Create proposal</button>
                    </p>
                </div>
            @endif
        @endif
    </div>

    <div class="modal-footer">
        <form method="POST" action="{{ route('deally.tasks.toggle', $task) }}" style="display: inline;">
            @csrf
            <button class="btn-sm" type="submit" @disabled($task->blockedReason() !== null && $task->status !== 'closed')
                title="{{ $task->blockedReason() ?? 'Close this task' }}">
                {{ $task->status === 'closed' ? 'Reopen' : 'Close task' }}
            </button>
        </form>
        <button class="btn-sm" type="button" data-close-modal>Dismiss</button>
        @if ($brief !== null)
            <a class="btn-sm primary" href="{{ route('deally.calls.review', $brief->call) }}">Open full review</a>
        @endif
    </div>
</div>
