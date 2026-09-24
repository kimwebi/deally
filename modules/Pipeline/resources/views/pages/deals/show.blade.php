@extends('core::layouts.app', [
    'pageTitle' => $opportunity->company,
    'pageSub' => ucfirst($opportunity->stage).' · '.($opportunity->packages ?: 'No package'),
])

@section('content')
<div class="list-page" style="max-width: 980px;">
    <div class="list-header">
        <div>
            <div class="list-title">Deal · {{ $opportunity->company }}</div>
            <div class="list-subtitle">
                @if ($customer)
                    <a href="{{ route('deally.customers.show', $customer) }}" style="color: var(--accent);">{{ $customer->company }} account</a>
                @else
                    No customer account linked
                @endif
                · ${{ number_format($opportunity->value) }} · owned by
                @if ($customer !== null && (int) $customer->owner_user_id === (int) auth()->id())
                    <strong>You</strong>
                @else
                    <strong>{{ $ownerName ?: '—' }}</strong>
                @endif
            </div>
        </div>
        <a href="{{ route('deally.pipeline') }}" class="btn-sm">← Pipeline</a>
    </div>

    @if ($assessed && $assessed['at_risk'])
        <div class="risk-panel">
            <div class="report-panel-title">⚠ At-risk account · {{ $assessed['label'] }}</div>
            <div class="risk-list">
                @foreach ($assessed['reasons'] as $reason)
                    <div class="risk-item">{{ $reason }}</div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($possibleLost)
        <div class="risk-panel" style="border-color: rgba(232, 162, 43, 0.3); background: rgba(232, 162, 43, 0.06);">
            <div class="report-panel-title">Possible lost deal</div>
            <div class="risk-list">
                <div class="risk-item">
                    This deal is flagged as possibly lost because its customer is at risk. The flag is resolved inside its
                    Review Call task — <a href="{{ route('deally.tasks.index') }}" style="color: var(--amber); text-decoration: underline;">open Tasks</a>.
                </div>
            </div>
        </div>
    @endif

    @if ($archived)
        <div class="risk-panel" style="border-color: rgba(232, 162, 43, 0.3); background: rgba(232, 162, 43, 0.06);">
            <div class="report-panel-title">Archived deal</div>
            <div class="risk-list">
                <div class="risk-item">Call transcripts and proposals archived. Contact admin to retrieve.</div>
            </div>
        </div>
    @endif

    <div class="settings-section">
        <div class="settings-title">Deal details</div>
        <div class="settings-sub">Move the deal between stages — every change is logged permanently</div>

        <form method="POST" action="{{ route('deally.deals.stage', $opportunity) }}" style="margin-top: 12px;">
            @csrf
            @method('PATCH')
            <div style="display:flex; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                <div class="field-block" style="min-width: 180px; flex: 1;">
                    <div class="field-label">Stage</div>
                    <select class="input-field" name="stage" id="deal-stage-select">
                        @foreach ($stages as $stage)
                            <option value="{{ $stage }}" @selected($opportunity->stage === $stage)>{{ ucfirst($stage) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-block" id="lost-reason-block" style="flex: 2; {{ $opportunity->stage === 'lost' ? '' : 'display:none;' }}">
                    <div class="field-label">Reason for losing <span style="color: var(--danger-bright);">*</span></div>
                    <input class="input-field" name="lost_reason" value="{{ $opportunity->lost_reason ?? '' }}" placeholder="e.g. Chose a competitor">
                    @error('lost_reason')
                        <div style="color: var(--danger-bright); font-size: 12px; margin-top: 6px;">{{ $message }}</div>
                    @enderror
                </div>
                <div style="padding-top: 22px;">
                    @if ($canManage)
                        <button class="btn-sm primary" type="submit">Save stage</button>
                    @else
                        <span style="font-size: 12px; color: var(--text-3);">View only</span>
                    @endif
                </div>
            </div>
        </form>
    </div>

    <div class="settings-section">
        <div class="settings-title">Engagement log</div>
        <div class="settings-sub">Calls, stage changes, proposals and notes — newest first</div>

        <div class="engagement-log">
            @forelse ($log as $row)
                @if ($row['type'] === 'call')
                    @php $call = $row['call']; @endphp
                    <div class="engagement-item">
                        <div class="engagement-marker">📞</div>
                        <div class="engagement-body">
                            <div class="engagement-head">
                                <strong>Call · {{ $call->name }}</strong>
                                <span class="mono">{{ $call->date->format('M d, Y g:ia') }}</span>
                            </div>
                            <div class="engagement-meta">
                                <span class="status-pill {{ match ($call->sentiment) { 'positive' => 'live', 'neutral' => 'pending', default => 'rejected' } }}">{{ ucfirst($call->sentiment) }}</span>
                                <span>{{ $call->duration ?: '—' }}</span>
                            </div>
                            @if ($call->summary)
                                <div class="engagement-summary">{{ $call->summary }}</div>
                            @endif
                            <a class="row-action primary" style="margin-top: 8px; text-decoration: none;" href="{{ route('deally.calls.show', $call) }}">View call</a>
                        </div>
                    </div>
                @elseif ($row['type'] === 'proposal')
                    @php $proposal = $row['proposal']; @endphp
                    <div class="engagement-item">
                        <div class="engagement-marker">📄</div>
                        <div class="engagement-body">
                            <div class="engagement-head">
                                <strong>Proposal · {{ $proposal->name }}</strong>
                                <span class="mono">{{ $proposal->updated_at->format('M d, Y') }}</span>
                            </div>
                            <div class="engagement-meta">
                                <span class="status-pill {{ match ($proposal->status) { 'approved' => 'won', 'rejected' => 'rejected', 'viewed' => 'viewed', default => 'pending' } }}">{{ ucfirst($proposal->status) }}</span>
                                <span class="mono">${{ number_format($proposal->value) }}</span>
                            </div>
                            <button class="row-action primary" style="margin-top: 8px;" data-fetch-proposal="{{ route('deally.proposals.show', $proposal) }}" data-title="{{ $proposal->name }}">Open proposal</button>
                        </div>
                    </div>
                @else
                    @php $event = $row['activity']; @endphp
                    <div class="engagement-item">
                        <div class="engagement-marker">📌</div>
                        <div class="engagement-body">
                            <div class="engagement-head">
                                <strong>{{ $event->event === 'opportunity.stage' ? 'Stage change' : 'Note' }}</strong>
                                <span class="mono">{{ $event->created_at->format('M d, Y g:ia') }}</span>
                            </div>
                            <div class="engagement-summary">{{ $event->description }}</div>
                        </div>
                    </div>
                @endif
            @empty
                <div style="color: var(--text-3); font-size: 13px; padding: 12px 0;">No activity logged for this deal yet.</div>
            @endforelse
        </div>

        @if ($canManage)
            <form method="POST" action="{{ route('deally.deals.notes', $opportunity) }}" class="note-form">
                @csrf
                <input class="input-field" name="note" placeholder="Add a note to the engagement log…" required>
                <button class="btn-sm primary" type="submit">Add note</button>
            </form>
        @endif
    </div>
</div>
@endsection

@push('modals')
<div class="modal-overlay" id="modal-proposal-detail">
    <div class="modal wide">
        <div class="modal-header">
            <div class="modal-header-icon proposal">📄</div>
            <div class="modal-header-body"><div class="modal-title">Proposal detail</div><div class="modal-subtitle" id="proposal-detail-subtitle">—</div></div>
            <button class="modal-close" data-close-modal>✕</button>
        </div>
        <div class="modal-body" id="proposal-detail-body">Loading…</div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var select = document.getElementById('deal-stage-select');
        var lostBlock = document.getElementById('lost-reason-block');
        if (select && lostBlock) {
            select.addEventListener('change', function () {
                lostBlock.style.display = this.value === 'lost' ? '' : 'none';
            });
        }
    });

    document.addEventListener('click', async function (e) {
        var trigger = e.target.closest('[data-fetch-proposal]');
        if (!trigger) return;

        try {
            var res = await fetch(trigger.getAttribute('data-fetch-proposal'), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) return;

            var body = document.getElementById('proposal-detail-body');
            var subtitle = document.getElementById('proposal-detail-subtitle');
            if (body) body.innerHTML = await res.text();
            if (subtitle && trigger.getAttribute('data-title')) subtitle.textContent = trigger.getAttribute('data-title');

            var modal = document.getElementById('modal-proposal-detail');
            if (modal) modal.classList.add('open');
        } catch (err) { /* modal stays closed on network errors */ }
    });
</script>
@endpush