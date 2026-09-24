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
        <div style="display: flex; gap: 10px; align-items: flex-start;">
            @if ($canManage && ! $opportunities->isEmpty())
                <span class="btn-sm primary" data-open-modal="modal-add-opportunity" style="cursor: pointer;">＋ New Deal</span>
            @endif
        </div>
    </div>

    <div class="list-toolbar">
        <a href="{{ route('deally.pipeline', ['view' => $view]) }}" class="filter-chip {{ $activeStage ? '' : 'active' }}">All</a>
        @foreach ($stages as $stage)
            <a href="{{ route('deally.pipeline', ['stage' => $stage, 'view' => $view]) }}" class="filter-chip {{ $activeStage === $stage ? 'active' : '' }}">{{ ucfirst($stage) }}</a>
        @endforeach

        <span style="flex: 1;"></span>

        <a href="{{ route('deally.pipeline', ['view' => 'list'] + (is_array($activeStage) ? [] : ['stage' => $activeStage])) }}"
           class="filter-chip {{ $view === 'list' ? 'active' : '' }}">List</a>
        <a href="{{ route('deally.pipeline', ['view' => 'board'] + (is_array($activeStage) ? [] : ['stage' => $activeStage])) }}"
           class="filter-chip {{ $view === 'board' ? 'active' : '' }}">Board</a>
    </div>

    @if ($view === 'board')
        <div class="board">
            @foreach ($stages as $stage)
                @php
                    $columnDeals = $opportunities->where('stage', $stage);
                @endphp
                <div class="board-column">
                    <div class="board-column-head">
                        <span>{{ ucfirst($stage) }}</span>
                        <span class="mono">{{ $columnDeals->count() }} · ${{ number_format($columnDeals->sum('value')) }}</span>
                    </div>
                    @forelse ($columnDeals as $opportunity)
                        @php $r = $assessments[$opportunity->customer_id] ?? null; @endphp
                        <a class="deal-card" href="{{ route('deally.deals.show', $opportunity) }}">
                            <div class="deal-card-customer">{{ $opportunity->company }}</div>
                            <div class="deal-card-meta">
                                {{ $opportunity->packages ?: 'No package' }}
                                @if ($opportunity->contact_name)
                                    · {{ $opportunity->contact_name }}
                                @endif
                            </div>
                            <div class="deal-card-foot">
                                <span class="mono" style="font-size: 12px;">${{ number_format($opportunity->value) }}</span>
                                @if ($r && $r['at_risk'])
                                    <span class="risk-badge {{ $r['tier'] }}" title="{{ implode(' · ', $r['reasons']) }}">{{ $r['label'] }}</span>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div style="font-size: 12px; color: var(--text-4); text-align: center; padding: 12px 0;">No deals</div>
                    @endforelse
                </div>
            @endforeach
        </div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Contact</th>
                    <th>Package(s)</th>
                    <th>Stage</th>
                    <th>Owner</th>
                    <th style="text-align:right;">Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($opportunities as $opportunity)
                    @php $r = $assessments[$opportunity->customer_id] ?? null; @endphp
                    <tr>
                        <td class="primary">
                            <a href="{{ route('deally.deals.show', $opportunity) }}" style="color: inherit; text-decoration: none;">
                                {{ $opportunity->company }}
                            </a>
                            @if ($r && $r['at_risk'])
                                <span class="risk-badge {{ $r['tier'] }}" style="margin-left: 8px;" title="{{ implode(' · ', $r['reasons']) }}">⚠ {{ $r['label'] }}</span>
                            @endif
                        </td>
                        <td>{{ $opportunity->contact_name ?: '—' }}{{ $opportunity->contact_title ? ' · '.$opportunity->contact_title : '' }}</td>
                        <td>{{ $opportunity->packages ?: '—' }}</td>
                        <td><span class="status-pill {{ $opportunity->stage }}">{{ ucfirst($opportunity->stage) }}</span></td>
                        <td>{{ $ownerNames[$opportunity->customer?->owner_user_id ?? null] ?? '—' }}</td>
                        <td class="mono" style="text-align:right;">${{ number_format($opportunity->value) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center; color: var(--text-3);">No deals yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif
</div>
@endsection

@push('modals')
<div class="modal-overlay" id="modal-add-opportunity">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon call">📊</div>
            <div class="modal-header-body"><div class="modal-title">New Deal</div><div class="modal-subtitle">Attach a deal to a customer account</div></div>
            <button class="modal-close" data-close-modal>✕</button>
        </div>
        <form method="POST" action="{{ route('deally.pipeline.store') }}">
            @csrf
            <div class="modal-body">
                <div class="field-block">
                    <div class="field-label">Customer <span style="color: var(--danger-bright);">*</span></div>
                    <select class="input-field" name="customer_id" required>
                        <option value="" disabled selected>Select a customer…</option>
                        @foreach ($customerPicker as $customerId => $company)
                            <option value="{{ $customerId }}">{{ $company }}</option>
                        @endforeach
                    </select>
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
                            <option value="{{ $stage }}" @selected($loop->first)>{{ ucfirst($stage) }}</option>
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

@if ($srSetupPrompt)
<script>
    (function () {
        var ok = window.confirm('Set up monthly Service Reviews with this customer?');
        if (ok) {
            var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            fetch('{{ route('deally.service-reviews.setup', $srSetupPrompt) }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            });
        }
    })();
</script>
@endif
@endpush