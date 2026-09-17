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
            <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                @csrf
                <button class="btn-sm" type="submit">Close</button>
            </form>
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

            <div class="add-task-card">
                <div class="add-task-icon">📋</div>
                <div class="add-task-text">
                    <div class="t1">Review this call in detail</div>
                    <div class="t2">Creates a <strong>"Review Call — {{ $call->company }}"</strong> task in your Tasks list.</div>
                </div>
                <form method="POST" action="{{ route('deally.calls.end', $call) }}">
                    @csrf
                    <input type="hidden" name="createTask" value="1">
                    <button class="add-task-btn" type="submit">＋ Add to Tasks</button>
                </form>
            </div>

            <div class="helper-note">Detailed review, transcript, and corrections live inside the task.</div>
        </div>
    </div>
</div>
@endsection