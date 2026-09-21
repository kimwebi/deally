@extends('core::layouts.app', [
    'pageTitle' => 'Calls',
    'pageSub' => 'Past recorded sessions · '.$calls->count().' total',
])

@php
    $retention = app(\Deally\Retention\Services\RetentionService::class);
@endphp

@section('content')
<div class="list-page">
    <div class="list-header">
        <div>
            <div class="list-title">Calls</div>
            <div class="list-subtitle">{{ $calls->count() }} recorded sessions · DeAlly transcribes and analyzes every call</div>
        </div>
        @if (! $calls->isEmpty())
            <span class="btn-sm primary" data-open-modal="modal-add-call" style="cursor: pointer;">＋ New Call</span>
        @endif
    </div>

    <table class="data-table">
        <thead>
            <tr><th>Call</th><th>Customer</th><th>Opportunity</th><th>Date</th><th>Duration</th><th>Sentiment</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($calls as $call)
                @php
                    $isArchived = $retention->isTranscriptArchived($call);
                    $sentimentPill = match ($call->sentiment) {
                        'positive' => 'live',
                        'neutral' => 'pending',
                        default => 'rejected',
                    };
                    $sentimentIcon = match ($call->sentiment) {
                        'positive' => '😊',
                        'neutral' => '😐',
                        default => '😟',
                    };
                @endphp
                <tr>
                    <td class="primary">{{ $call->name }}</td>
                    <td>{{ $call->company }}</td>
                    <td>{{ $call->opportunity?->company ?? '—' }}</td>
                    <td class="mono">{{ $call->date->format('M d') }}</td>
                    <td class="mono">{{ $call->duration ?: '—' }}</td>
                    <td>
                        <span class="status-pill {{ $sentimentPill }}">{{ $sentimentIcon }} {{ ucfirst($call->sentiment) }}</span>
                    </td>
                    <td style="text-align:right;">
                        @if ($isArchived)
                            <span style="font-size: 12px; color: var(--amber);">🔒 Archived</span>
                        @else
                            <a href="{{ route('deally.calls.show', $call) }}" class="row-action primary">View</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center; color: var(--text-3);">No calls recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($calls->contains(fn ($call) => $retention->isTranscriptArchived($call)))
        <div class="risk-panel" style="border-color: rgba(232, 162, 43, 0.3); background: rgba(232, 162, 43, 0.06);">
            <div class="report-panel-title">Archive notice</div>
            <div class="risk-list">
                <div class="risk-item">Call transcripts and proposals archived. Contact admin to retrieve.</div>
            </div>
        </div>
    @endif
</div>
@endsection

@push('modals')
<div class="modal-overlay" id="modal-add-call">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon call">📞</div>
            <div class="modal-header-body"><div class="modal-title">New Call</div><div class="modal-subtitle">Starts a DeAlly live session</div></div>
            <button class="modal-close" data-close-modal>✕</button>
        </div>
        <form method="POST" action="{{ route('deally.calls.store') }}">
            @csrf
            <div class="modal-body">
                <div class="field-block">
                    <div class="field-label">Call name</div>
                    <input class="input-field" name="name" placeholder="Demo & Discovery" required>
                </div>
                <div class="field-block">
                    <div class="field-label">Company</div>
                    <input class="input-field" name="company" placeholder="Acme Corp" required>
                </div>
                <div class="field-block">
                    <div class="field-label">Contact</div>
                    <input class="input-field" name="contact_name" placeholder="Jane Doe">
                </div>
                <div class="field-block">
                    <div class="field-label">Date</div>
                    <input class="input-field" type="date" name="date" value="{{ today()->toDateString() }}">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-sm" type="button" data-close-modal>Cancel</button>
                <button class="btn-sm primary" type="submit">Start Call</button>
            </div>
        </form>
    </div>
</div>
@endpush