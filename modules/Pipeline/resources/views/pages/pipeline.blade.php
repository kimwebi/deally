@extends('core::layouts.app', [
    'pageTitle' => 'Pipeline',
    'pageSub' => $opportunities->count().' opportunities · $'.number_format($totalValue).' total',
])

@section('content')
<div class="list-page">
    <div class="list-header">
        <div>
            <div class="list-title">Pipeline</div>
            <div class="list-subtitle">{{ $opportunities->count() }} opportunities · ${{ number_format($totalValue) }} total pipeline value</div>
        </div>
        @if (! $opportunities->isEmpty())
            <span class="btn-sm primary" data-open-modal="modal-add-opportunity" style="cursor: pointer;">＋ New Deal</span>
        @endif
    </div>

    <div class="list-toolbar">
        <a href="{{ route('deally.pipeline') }}" class="filter-chip {{ $activeStage ? '' : 'active' }}">All</a>
        @foreach ($stages as $stage)
            <a href="{{ route('deally.pipeline', ['stage' => $stage]) }}" class="filter-chip {{ $activeStage === $stage ? 'active' : '' }}">{{ ucfirst($stage) }}</a>
        @endforeach
    </div>

    <table class="data-table">
        <thead>
            <tr><th>Customer</th><th>Contact</th><th>Package(s)</th><th>Stage</th><th style="text-align:right;">Value</th></tr>
        </thead>
        <tbody>
            @forelse ($opportunities as $opportunity)
                <tr>
                    <td class="primary">{{ $opportunity->company }}</td>
                    <td>{{ $opportunity->contact_name ?: '—' }}{{ $opportunity->contact_title ? ' · '.$opportunity->contact_title : '' }}</td>
                    <td>{{ $opportunity->packages ?: '—' }}</td>
                    <td><span class="status-pill {{ $opportunity->stage }}">{{ ucfirst($opportunity->stage) }}</span></td>
                    <td class="mono" style="text-align:right;">${{ number_format($opportunity->value) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center; color: var(--text-3);">No deals yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('modals')
<div class="modal-overlay" id="modal-add-opportunity">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon call">📊</div>
            <div class="modal-header-body"><div class="modal-title">New Deal</div><div class="modal-subtitle">Add an opportunity to the pipeline</div></div>
            <button class="modal-close" data-close-modal>✕</button>
        </div>
        <form method="POST" action="{{ route('deally.pipeline.store') }}">
            @csrf
            <div class="modal-body">
                <div class="field-block">
                    <div class="field-label">Company</div>
                    <input class="input-field" name="company" required>
                </div>
                <div class="field-block">
                    <div class="field-label">Contact</div>
                    <input class="input-field" name="contact_name" placeholder="Jane Doe">
                </div>
                <div class="field-block">
                    <div class="field-label">Contact title</div>
                    <input class="input-field" name="contact_title" placeholder="CTO">
                </div>
                <div class="field-block">
                    <div class="field-label">Package(s)</div>
                    <input class="input-field" name="packages" placeholder="Enterprise Suite (3)">
                </div>
                <div class="field-block">
                    <div class="field-label">Stage</div>
                    <select class="input-field" name="stage">
                        @foreach ($stages as $stage)
                            <option value="{{ $stage }}">{{ ucfirst($stage) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-block">
                    <div class="field-label">Value ($)</div>
                    <input class="input-field" type="number" name="value" min="0" step="0.01">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-sm" type="button" data-close-modal>Cancel</button>
                <button class="btn-sm primary" type="submit">Save Deal</button>
            </div>
        </form>
    </div>
</div>
@endpush