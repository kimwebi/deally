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
            <div class="list-subtitle">{{ $calls->count() }} recorded sessions · DeAlly transcribes and analyzes every call · <a href="{{ route('docs.calls') }}" target="_blank" style="color: var(--primary); text-decoration: underline;">Docs</a></div>
        </div>
        <span class="btn-sm primary" data-open-modal="modal-add-call" style="cursor: pointer;">＋ New Call</span>
    </div>

    <table class="data-table">
        <thead>
            <tr><th>Call</th><th>Customer</th><th>Agent</th><th>Opportunity</th><th>Date</th><th>Duration</th><th>Sentiment</th><th></th></tr>
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
                    <td>{{ $call->owner_user_id === auth()->id() ? 'You' : ($ownerNames[$call->owner_user_id] ?? '—') }}</td>
                    <td>{{ $call->opportunity?->company ?? '—' }}</td>
                    <td class="mono">{{ $call->date->format('M d') }}</td>
                    <td class="mono">{{ $call->duration ?: '—' }}</td>
                    <td>
                        <span class="status-pill {{ $sentimentPill }}">{{ $sentimentIcon }} {{ ucfirst($call->sentiment) }}</span>
                    </td>
                    <td style="text-align:right;">
                        @if ($isArchived)
                            <span style="font-size: 12px; color: var(--amber);"><i class="bi bi-archive-fill" style="color: darkgoldenrod"></i> Archived</span>
                        @else
                            {{-- The detail is a modal, not a page. The body is
                                 fetched on demand so the list does not carry a
                                 full transcript for every row. --}}
                            <span class="row-action primary" style="cursor: pointer;"
                                data-open-modal="modal-call-detail"
                                data-modal-url="{{ route('deally.calls.detail', $call) }}"
                                data-review-url="{{ route('deally.calls.review', $call) }}">View</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center; color: var(--text-3);">No calls recorded yet.</td></tr>
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
<div class="modal-overlay" id="modal-call-detail">
    {{-- One reusable shell. Its body is replaced with the fetched fragment each
         time a row is opened, so the last call viewed is what stays on screen if
         the modal is reopened without a fetch. --}}
    <div id="modal-call-detail-host" data-modal-host></div>
</div>

<div class="modal-overlay" id="modal-add-call">
    <div class="modal wide">
        <div class="modal-header">
            <div class="modal-header-icon call"><i class="bi bi-telephone-fill" style="color: #ec4899"></i></div>
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
                    <div class="field-label">Customer</div>
                    {{-- The customer is a real account, picked from the list —
                         not a free-typed company. Company and contact on the
                         call come from the chosen account so the two can never
                         drift apart. --}}
                    <select class="input-field" name="customer_id" id="call-customer-select">
                        <option value="">Pick a customer…</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->getKey() }}"
                                @if ((string) old('customer_id') === (string) $customer->getKey()) selected @endif
                                data-contact-name="{{ $customer->contact_name ?? '' }}"
                                data-contact-title="{{ $customer->contact_title ?? '' }}"
                                data-deals='@json($customer->opportunities->map(fn ($deal): array => [
                                    'id' => $deal->getKey(),
                                    'label' => $deal->company.' · '.ucfirst((string) $deal->stage).($deal->value !== null ? ' · $'.number_format((float) $deal->value) : ''),
                                ])->values())'>{{ $customer->company }}</option>
                        @endforeach
                    </select>
                    @if ($canManageCustomers)
                        <p class="field-hint" style="margin-top: 6px;">
                            <a href="#" id="call-new-customer-toggle"
                                data-text-off="＋ Add a new customer"
                                data-text-on="‹ Pick an existing customer">＋ Add a new customer</a>
                            — or pick an existing account above.
                        </p>
                    @endif
                </div>

                <div class="field-block" id="call-new-customer-fields" style="{{ old('new_customer_company') ? 'display: block;' : 'display: none;' }}">
                    <div class="field-label">New customer</div>
                    <input class="input-field" name="new_customer_company" placeholder="Company — e.g. Acme Corp" value="{{ old('new_customer_company') }}">
                    <input class="input-field" name="new_customer_contact_name" placeholder="Contact (optional)" value="{{ old('new_customer_contact_name') }}" style="margin-top: 8px;">
                    <input class="input-field" name="new_customer_contact_title" placeholder="Job title (optional)" value="{{ old('new_customer_contact_title') }}" style="margin-top: 8px;">
                </div>

                <div class="field-block" id="call-deal-block" style="display: none;">
                    <div class="field-label">Deal <span style="font-weight: 400; color: var(--text-4);">(optional)</span></div>
                    <select class="input-field" name="opportunity_id" id="call-deal-select">
                        <option value="">No deal attached</option>
                    </select>
                    <p class="field-hint">The call is filed under the customer; attach the deal it belongs to if there is one.</p>
                </div>

                <div class="field-block">
                    <div class="field-label">Contact</div>
                    <input class="input-field" name="contact_name" id="call-contact-name" placeholder="Jane Doe" value="{{ old('contact_name') }}">
                </div>
                <div class="field-block">
                    {{-- The customer's role is their job title, typed by the rep,
                         not a value from the account's role system. It is
                         prefilled from the account but stays editable — a call
                         can be with another person at the same company. --}}
                    <div class="field-label">Contact job title</div>
                    <input class="input-field" name="contact_role" id="call-contact-role" placeholder="e.g. CTO, VP Engineering" value="{{ old('contact_role') }}">
                    <p class="field-hint">Shown on the live-call context card and the call detail.</p>
                </div>
                <div class="field-block">
                    <div class="field-label">Date</div>
                    <input class="input-field" type="date" name="date" value="{{ today()->toDateString() }}">
                </div>
                <div class="field-block">
                    <div class="field-label">What kind of call</div>
                    <select class="input-field" name="session_type">
                        @foreach (['discovery' => 'Discovery', 'demo' => 'Demo', 'service_review' => 'Service review', 'follow_up' => 'Follow-up'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-block">
                    <div class="field-label">Meeting platform</div>
                    @if ($platforms->isEmpty())
                        {{-- No platforms enabled is a legitimate state, and the
                             picker says so rather than offering something that
                             cannot create a meeting. --}}
                        <p class="field-hint">No meeting platform is enabled for this account. The call runs in DeAlly's own live session.</p>
                    @else
                        <div class="platform-picker" role="radiogroup" aria-label="Meeting platform">
                            @foreach ($platforms as $platform)
                                <label class="platform-option {{ $platform->isUsable() ? 'is-usable' : 'is-unconnected' }}">
                                    <input type="radio" name="meeting_platform" value="{{ $platform->key }}"
                                        {{ $platform->isUsable() ? '' : 'data-unconnected="true"' }}
                                        data-note-target="platform-note-{{ $platform->key }}">
                                    <span class="platform-option-icon">{{ $platform->icon }}</span>
                                    <span class="platform-option-body">
                                        <span class="platform-option-name">{{ $platform->name }}</span>
                                        @unless ($platform->isUsable())
                                            {{-- Stated here, not hidden: a rep needs to
                                                 know the bot will not be admitted
                                                 before the customer is invited. --}}
                                            <span class="platform-option-note">Not connected — {{ $platform->connection_note }}</span>
                                        @endunless
                                    </span>
                                </label>
                            @endforeach
                            <label class="platform-option is-usable">
                                <input type="radio" name="meeting_platform" value="" checked>
                                <span class="platform-option-icon"><i class="bi bi-telephone-fill" style="color: #ec4899"></i></span>
                                <span class="platform-option-body">
                                    <span class="platform-option-name">DeAlly live session</span>
                                    <span class="platform-option-note">No meeting platform. The customer joins the audio directly.</span>
                                </span>
                            </label>
                        </div>
                    @endif
                </div>
                <div class="field-block">
                    <div class="field-label">Invite the customer by email</div>
                    <input class="input-field" type="email" name="invite_email" placeholder="jane@acme.com">
                    <p class="field-hint">
                        The exact wording is stored on the call, so what was sent can be checked later.
                    </p>
                </div>
                <div class="field-block">
                    <div class="field-label">Assign to</div>
                    <select class="input-field" name="assignee_user_id">
                        <option value="">Me — {{ auth()->user()->name }}</option>
                        @php
                            $suggestedForAssignment = $suggestedOwners ?? collect();
                            $remainingAssignees = $assignees->reject(fn ($name, $id) => $suggestedForAssignment->has($id));
                        @endphp
                        @if ($suggestedForAssignment->isNotEmpty())
                            <optgroup label="Suggested · your team">
                                @foreach ($suggestedForAssignment as $assigneeId => $assigneeName)
                                    @if ($assigneeId !== auth()->id())
                                        <option value="{{ $assigneeId }}">{{ $assigneeName }}</option>
                                    @endif
                                @endforeach
                            </optgroup>
                        @endif
                        @if ($remainingAssignees->isNotEmpty())
                            <optgroup label="Everyone else">
                                @foreach ($remainingAssignees as $assigneeId => $assigneeName)
                                    @if ($assigneeId !== auth()->id())
                                        <option value="{{ $assigneeId }}">{{ $assigneeName }}</option>
                                    @endif
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-sm" type="button" data-close-modal>Cancel</button>
                <button class="btn-sm primary" type="submit">Start Call</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var select = document.getElementById('call-customer-select');
    if (!select) { return; }

    var contactName = document.getElementById('call-contact-name');
    var contactRole = document.getElementById('call-contact-role');
    var dealBlock = document.getElementById('call-deal-block');
    var dealSelect = document.getElementById('call-deal-select');
    var toggle = document.getElementById('call-new-customer-toggle');
    var newFields = document.getElementById('call-new-customer-fields');

    function inNewMode() {
        return newFields !== null && newFields.style.display !== 'none';
    }

    function dealsOf(option) {
        try { return JSON.parse(option.dataset.deals || '[]'); } catch (e) { return []; }
    }

    function refresh() {
        if (inNewMode()) {
            contactName.value = '';
            contactRole.value = '';
            dealBlock.style.display = 'none';
            return;
        }

        var option = select.selectedOptions[0];

        if (!option || !option.value) {
            contactName.value = '';
            contactRole.value = '';
            dealBlock.style.display = 'none';
            return;
        }

        contactName.value = option.dataset.contactName || '';
        contactRole.value = option.dataset.contactTitle || '';

        var deals = dealsOf(option);
        dealSelect.innerHTML = '<option value="">No deal attached</option>' + deals.map(function (deal) {
            return '<option value="' + deal.id + '">' + deal.label + '</option>';
        }).join('');
        dealBlock.style.display = deals.length ? 'block' : 'none';
    }

    select.addEventListener('change', refresh);

    if (toggle !== null && newFields !== null) {
        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            var active = !inNewMode();
            newFields.style.display = active ? 'block' : 'none';
            select.disabled = active;
            toggle.textContent = active ? toggle.dataset.textOn : toggle.dataset.textOff;
            refresh();
            if (active) {
                var first = newFields.querySelector('input');
                if (first) { first.focus(); }
            }
        });
    }

    refresh();
})();
</script>
@endpush
