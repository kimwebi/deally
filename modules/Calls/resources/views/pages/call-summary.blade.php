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
                    @php
                        $sentPositive = $call->sentiment === 'positive' || $call->sentiment === null;
                    @endphp
                    <span class="sentiment-pill {{ $sentPositive ? 'positive' : 'warm' }}">
                        {{ $sentPositive ? '😊 Positive sentiment' : '😐 Neutral sentiment' }}
                    </span>
                    <span class="sentiment-pill warm">🔥 Warm — high intent</span>
                </div>
            </div>

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
                    <a class="btn-sm" href="{{ route('deally.tasks.index') }}">View task</a>
                    <a class="btn-sm primary" href="{{ route('deally.calls.review', $call) }}">Open full review</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection