@extends('core::layouts.app', [
    'pageTitle' => 'Proposals',
    'pageSub' => $proposals->count().' total · $'.number_format($inflight).' in-flight',
])

@section('content')
<div class="list-page">
    <div class="list-header">
        <div>
            <div class="list-title">Proposals</div>
            <div class="list-subtitle">{{ $proposals->count() }} total · ${{ number_format($inflight) }} in-flight</div>
        </div>
        @if (! $proposals->isEmpty())
            <span class="btn-sm primary" data-open-modal="modal-add-proposal" style="cursor: pointer;">＋ New Proposal</span>
        @endif
    </div>

    <table class="data-table">
        <thead>
            <tr><th>Proposal</th><th>Customer</th><th>Value</th><th>Status</th><th>Updated</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($proposals as $proposal)
                @php
                    $isArchived = in_array($proposal->id, $archivedIds, true);
                @endphp
                <tr>
                    <td class="primary">{{ $proposal->name }}</td>
                    <td>{{ $proposal->company }}</td>
                    <td class="mono">${{ number_format($proposal->value) }}</td>
                    <td>
                        @php
                            $pill = match ($proposal->status) {
                                'approved' => 'won',
                                'rejected' => 'rejected',
                                'viewed' => 'viewed',
                                default => 'pending',
                            };
                        @endphp
                        @if ($isArchived)
                            <span class="status-pill closed">Archived</span>
                        @else
                            <span class="status-pill {{ $pill }}">{{ ucfirst($proposal->status) }}</span>
                        @endif
                    </td>
                    <td class="mono">{{ $proposal->updated_at->format('M d') }}</td>
                    <td style="text-align:right;">
                        @if ($isArchived)
                            <span style="font-size: 12px; color: var(--amber);">🔒 Archived</span>
                        @else
                            <button class="row-action primary" data-open-modal="modal-proposal"
                                data-title="{{ $proposal->name }}"
                                data-subtitle="{{ $proposal->company }} · {{ ucfirst($proposal->status) }}"
                                data-company="{{ $proposal->company }}"
                                data-value="${{ number_format($proposal->value) }}">View &amp; Edit</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center; color: var(--text-3);">No proposals yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($archivedIds !== [])
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
<div class="modal-overlay" id="modal-add-proposal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon proposal">📄</div>
            <div class="modal-header-body"><div class="modal-title">New Proposal</div><div class="modal-subtitle">Draft a proposal</div></div>
            <button class="modal-close" data-close-modal>✕</button>
        </div>
        <form method="POST" action="{{ route('deally.proposals.store') }}">
            @csrf
            <div class="modal-body">
                <div class="field-block">
                    <div class="field-label">Proposal name</div>
                    <input class="input-field" name="name" placeholder="Acme Enterprise v2" required>
                </div>
                <div class="field-block">
                    <div class="field-label">Customer</div>
                    <input class="input-field" name="company" placeholder="Acme Corp" required>
                </div>
                <div class="field-block">
                    <div class="field-label">Value ($)</div>
                    <input class="input-field" type="number" name="value" min="0" step="0.01">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-sm" type="button" data-close-modal>Cancel</button>
                <button class="btn-sm primary" type="submit">Save Proposal</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modal-proposal">
    <div class="modal wide">
        <div class="modal-header">
            <div class="modal-header-icon proposal">📄</div>
            <div class="modal-header-body">
                <div class="modal-title" data-fill="title">Proposal</div>
                <div class="modal-subtitle" data-fill="subtitle">—</div>
            </div>
            <button class="modal-close" data-close-modal>✕</button>
        </div>
        <div class="modal-body">
            <div class="proposal-preview">
                <h4>Proposal For</h4>
                <h3 data-fill="company">Customer</h3>
                <div class="prepared">Prepared for the buying team · {{ today()->format('F j, Y') }}</div>
                <div class="quote">"As you mentioned during our call, ensuring stability under load is critical."</div>
                <div class="section-label-inner">Recommended Solution</div>
                <div class="line-item"><span>Enterprise Suite — 500 users</span><span class="mono">$7,500/mo</span></div>
                <div class="line-item"><span>Custom Onboarding</span><span class="mono">Included</span></div>
                <div class="total"><span>Annual Total</span><span data-fill="value">$—</span></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-sm" data-close-modal>Close</button>
        </div>
    </div>
</div>
@endpush
