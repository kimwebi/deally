@extends('core::layouts.call', ['title' => 'Call Summary · '.$call->company])

@section('content')
<div class="post-shell">
    <div class="post-header">
        <div class="post-check">✓</div>
        <div>
            <div class="post-title">Call Summary</div>
            <div class="post-meta">{{ $call->company }} · {{ $call->duration ?: '0m' }} · {{ $call->date->format('M d, Y') }}</div>
        </div>
        <div class="post-actions-top">
            <a href="{{ route('deally.calls.show', $call) }}" class="btn-sm">Transcript</a>
            <a href="{{ route('deally.workspace') }}" class="btn-sm">Close</a>
        </div>
    </div>

    <div class="post-body">
        <div class="post-content">
            <div class="card">
                <div class="card-label"><span class="dot"></span>Summary</div>
                <div class="summary-text">{{ $call->summary ?: ($call->contact_name ?: 'The customer').' discussed needs for '.$call->company.'. DeAlly captured the transcript, AI interventions, and follow-ups.' }}</div>

                @if ($call->notes)
                    <div class="agent-note">
                        <div class="agent-note-label">Your note from call</div>
                        "{{ $call->notes }}"
                    </div>
                @endif

                <div class="sentiment-row">
                    {{-- Both reads are the ones actually stored. This used to
                         hardcode "Warm — high intent" for every call, which is
                         the single most misleading line on the page a rep lands
                         on after hanging up. --}}
                    <span class="sentiment-pill {{ $call->effectiveSentiment() === 'positive' ? 'positive' : 'warm' }}">
                        {{ match ($call->effectiveSentiment()) {
                            'positive' => '😊 Positive sentiment',
                            'negative' => '😟 Negative sentiment',
                            default => '😐 Neutral sentiment',
                        } }}
                    </span>

                    <span class="sentiment-pill {{ $call->effectiveReadiness() === 'cold' ? 'cool' : 'warm' }}">
                        {{ match ($call->effectiveReadiness()) {
                            'hot' => '🔥 Hot — ready to close',
                            'cold' => '🧊 Cold — not close to a decision',
                            default => '🌤 Warm — engaged, not committed',
                        } }}
                    </span>

                    @if ($call->sentimentWasCorrected() || $call->readinessWasCorrected())
                        <span class="sentiment-pill corrected">✎ Corrected by a rep</span>
                    @endif
                </div>

                @if ($call->proposal_intent)
                    <div class="proposal-intent">
                        <div class="proposal-intent-label">Agreed on this call</div>
                        <div class="proposal-intent-text">
                            A proposal was the next step{{ $call->proposal_intent_note ? ' — '.$call->proposal_intent_note : '' }}.
                            <button type="button" class="link-btn" id="create-proposal-btn"
                                data-proposal-url="{{ route('deally.calls.proposal', $call) }}">Create proposal</button>
                        </div>
                    </div>
                @endif
            </div>

            @if ($openFlags->isNotEmpty())
                <div class="add-task-card blocked">
                    <div class="add-task-icon">⚑</div>
                    <div class="add-task-text">
                        <div class="t1">{{ $openFlags->count() === 1 ? 'One deal-status flag' : $openFlags->count().' deal-status flags' }} on this call</div>
                        <div class="t2">
                            The review task cannot be closed until {{ $openFlags->count() === 1 ? 'this is' : 'these are' }} resolved.
                            @foreach ($openFlags as $openFlag)
                                <div class="t2-flag">• {{ $openFlag->headline }}</div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <div class="add-task-card done">
                <div class="add-task-icon">✓</div>
                <div class="add-task-text">
                    <div class="t1">"Review Call — {{ $call->company }}" added to your tasks</div>
                    <div class="t2">
                        Reviewed within {{ $reviewTask?->due_at ? '24h (due '.$reviewTask->due_at->format('M d, g:ia').')' : '24 hours' }}.
                        The transcript, AI guidance, and gaps are attached for the deep review.
                    </div>
                </div>
                <div class="review-actions">
                    {{-- Opens the task itself, not the tasks index. Sending a
                         rep to a list to find the one task this page just
                         created wastes the only moment they are looking for it. --}}
                    @if ($reviewTask)
                        <span class="btn-sm" style="cursor: pointer;"
                            data-open-modal="modal-task"
                            data-modal-url="{{ route('deally.tasks.show', $reviewTask) }}"
                            data-review-url="{{ route('deally.calls.review', $call) }}">View task</span>
                    @endif
                    <a class="btn-sm primary" href="{{ route('deally.calls.review', $call) }}">Open full review</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
{{-- The same shell the tasks list uses, so the review reads identically
     whichever route the rep arrived by. --}}
<div class="modal-overlay" id="modal-task">
    <div id="modal-task-host" data-modal-host></div>
</div>
@endpush