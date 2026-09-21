@extends('core::layouts.app', [
    'pageTitle' => $call->name.' · '.$call->company,
    'pageSub' => $call->date->format('M d').' · '.($call->duration ?: '0m'),
])

@php($archived = app(\Deally\Retention\Services\RetentionService::class)->isTranscriptArchived($call))
@section('content')
<div class="list-page" style="max-width: 860px;">
    <div class="list-header">
        <div>
            <div class="list-title">{{ $call->name }}</div>
            <div class="list-subtitle">
                {{ $call->company }} · {{ $call->date->format('M d, Y') }}
                @if ($call->contact_name)
                    · {{ $call->contact_name }}{{ $call->contact_role ? ' ('.$call->contact_role.')' : '' }}
                @endif
            </div>
        </div>
        <div class="post-actions-top">
            @if (! $archived)
                <a href="{{ route('deally.calls.summary', $call) }}" class="btn-sm">Summary</a>
                <a href="{{ route('deally.calls.live', $call) }}" class="btn-sm primary">Replay Live</a>
            @endif
        </div>
    </div>

    @if ($archived)
        <div class="risk-panel" style="border-color: rgba(232, 162, 43, 0.3); background: rgba(232, 162, 43, 0.06);">
            <div class="report-panel-title">Archive notice</div>
            <div class="risk-list">
                <div class="risk-item">Call transcripts and proposals archived. Contact admin to retrieve.</div>
            </div>
        </div>
    @endif

    <div class="settings-section">
        <div class="settings-title">Transcript</div>
        <div class="settings-sub">{{ $call->transcriptLines->count() }} lines · DeAlly highlights key moments inline</div>

        <div class="modal-body" style="padding: 0;">
            @forelse ($call->transcriptLines as $line)
                <div class="transcript-line" id="line-{{ $line->sequence }}">
                    <div class="speaker {{ $line->is_agent ? 'agent' : '' }}">{{ $line->is_agent ? 'Agent' : $line->speaker }}</div>
                    <div class="transcript-text">{{ $line->text }}</div>
                </div>
                @if ($line->linked_text)
                    <div class="transcript-linked {{ $line->linked_type === 'competitor' ? 'competitor' : ($line->linked_type === 'correction' ? 'warning' : '') }}">
                        {{ $line->linked_type === 'competitor' ? '🔵 Battle card shown' : '⚪ '.$line->linked_text }}{{ ! $line->linked_type || $line->linked_type === 'competitor' ? '' : '' }}
                    </div>
                @endif
            @empty
                <div style="font-size: 13px; color: var(--text-3); padding: 10px 0;">
                    No transcript lines saved yet. <a href="{{ route('deally.calls.live', $call) }}" style="color: var(--violet);">Run the live session</a> to capture one.
                </div>
            @endforelse
        </div>
    </div>

    @if ($call->summary)
        <div class="settings-section">
            <div class="settings-title">Summary</div>
            <div class="summary-text">{{ $call->summary }}</div>
        </div>
    @endif
</div>
@endsection