@extends('core::layouts.app', [
    'pageTitle' => $customer->company,
    'pageSub' => $customer->contact_name ? $customer->contact_name.($customer->contact_title ? ' · '.$customer->contact_title : '') : 'No contact on file',
])

@section('content')
<div class="list-page" style="max-width: 980px;">
    <div class="list-header">
        <div>
            <div class="list-title">{{ $customer->company }}</div>
            <div class="list-subtitle">
                Owned by
                @if ((int) $customer->owner_user_id === (int) auth()->id())
                    <strong>You</strong>
                @else
                    <strong>{{ $ownerNames[$customer->owner_user_id] ?? '—' }}</strong>
                @endif
                · {{ $teamNames[$customer->team_id] ?? 'no team' }}
                · {{ $openDealsCount }} open deal{{ $openDealsCount === 1 ? '' : 's' }} · ${{ number_format($openValue) }} open value
            </div>
        </div>
        <div class="post-actions-top">
            <a href="{{ route('deally.customers.index') }}" class="btn-sm">← Customers</a>
            @if ($canManage)
                <span class="btn-sm primary" data-open-modal="modal-edit-customer" style="cursor: pointer;">Edit</span>
            @endif
        </div>
    </div>

    @if ($risk['at_risk'])
        <div class="risk-panel">
            <div class="report-panel-title">⚠ At-risk account · {{ $risk['label'] }}</div>
            <div class="risk-list">
                @foreach ($risk['reasons'] as $reason)
                    <div class="risk-item">{{ $reason }}</div>
                @endforeach
                @if ($schedule !== null && $srMissed->isNotEmpty())
                    <div class="risk-item">
                        {{ $srMissed->count() }} missed Service Review session{{ $srMissed->count() === 1 ? '' : 's' }} —
                        @if ($canManage)
                            <span class="row-action primary" data-open-modal="modal-sr-catchup" style="cursor: pointer; text-decoration: underline;">schedule a catch-up review</span>
                        @else
                            contact the owning agent to schedule a catch-up.
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="settings-section">
        <div class="settings-title">Contacts</div>
        <div class="settings-sub">{{ $contacts->count() }} people at {{ $customer->company }} — every contact lives on this one account (no duplicate customers)</div>

        <table class="data-table">
            <thead>
                <tr><th>Name</th><th>Title</th><th>Email</th><th>Phone</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($contacts as $contact)
                    <tr>
                        <td class="primary">
                            {{ $contact->name }}
                            @if ($contact->is_primary)
                                <span class="status-pill live" style="margin-left: 8px;">Primary</span>
                            @endif
                        </td>
                        <td>{{ $contact->title ?: '—' }}</td>
                        <td>{{ $contact->email ?: '—' }}</td>
                        <td>{{ $contact->phone ?: '—' }}</td>
                        <td style="text-align:right;"></td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center; color: var(--text-3);">No contacts on file yet.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($canManage)
            <span class="btn-sm primary" data-open-modal="modal-add-contact" style="cursor: pointer; margin-top: 10px;">＋ Add Contact</span>
        @endif
    </div>

    <div class="settings-section">
        <div class="settings-title">Service Review</div>
        <div class="settings-sub">
            @if ($schedule !== null)
                Every {{ $schedule->cadence_days }} days
                @if ($srNext)
                    · next review {{ $srNext->scheduled_at->format('M d, Y g:ia') }}
                @endif
                · {{ $srHeld->count() }} held · {{ $srMissed->count() }} missed
            @else
                No active Service Review schedule
            @endif
        </div>

        @if ($schedule !== null)
            @if ($srUpcoming->isNotEmpty())
                <div style="margin-top: 10px;">
                    <div style="font-size: 12px; font-weight: 600; color: var(--text-3); margin-bottom: 6px;">Upcoming sessions</div>
                    @foreach ($srUpcoming as $session)
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--border-soft); font-size: 13px;">
                            <span>{{ $session->scheduled_at->format('M d, Y g:ia') }}</span>
                            <span style="display: flex; gap: 8px;">
                                @if ($canManage)
                                    <span class="row-action primary" data-open-modal="modal-sr-reschedule" data-session-url="{{ route('deally.service-reviews.sessions.reschedule', $session) }}" data-session-time="{{ $session->scheduled_at->format('Y-m-d\TH:i') }}" style="cursor: pointer;">Reschedule</span>
                                    <form method="POST" action="{{ route('deally.service-reviews.sessions.cancel', $session) }}" style="display: inline;"
                                          onsubmit="return confirm('Cancel this review session? It will not count as missed.');">
                                        @csrf
                                        <button class="row-action primary" style="background:none;border:none;cursor:pointer;font-size:13px;padding:0;" type="submit">Cancel</button>
                                    </form>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($srMissed->isNotEmpty())
                <div style="margin-top: 14px;">
                    <div style="font-size: 12px; font-weight: 600; color: var(--red); margin-bottom: 6px;">Missed sessions (drive the at-risk flag)</div>
                    @foreach ($srMissed as $session)
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--border-soft); font-size: 13px;">
                            <span><span class="status-pill rejected">Missed</span> {{ $session->scheduled_at->format('M d, Y g:ia') }}</span>
                            @if ($canManage)
                                <form method="POST" action="{{ route('deally.service-reviews.sessions.hold', $session) }}" style="display: inline;">
                                    @csrf
                                    <button class="row-action primary" style="background:none;border:none;cursor:pointer;font-size:13px;padding:0;" type="submit">Mark held</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($srHeld->isNotEmpty())
                <div style="margin-top: 14px;">
                    <div style="font-size: 12px; font-weight: 600; color: var(--text-3); margin-bottom: 6px;">Held sessions</div>
                    @foreach ($srHeld as $session)
                        <div style="padding: 8px 0; border-bottom: 1px solid var(--border-soft); font-size: 13px;">
                            <span class="status-pill live">Held</span> {{ $session->scheduled_at->format('M d, Y g:ia') }}
                            @if ($session->notes)
                                <div style="font-size: 12px; color: var(--text-3); margin-top: 3px;">{{ $session->notes }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($canManage)
                <div style="display: flex; gap: 10px; margin-top: 14px; flex-wrap: wrap;">
                    <span class="btn-sm" data-open-modal="modal-sr-cadence" style="cursor: pointer;">Edit cadence</span>
                    <span class="btn-sm" data-open-modal="modal-sr-catchup" style="cursor: pointer;">＋ Catch-up review</span>
                    <form method="POST" action="{{ route('deally.service-reviews.end', $schedule) }}" style="display: inline;"
                          onsubmit="return confirm('End this Service Review schedule? All future sessions are removed and no more will be scheduled.');">
                        @csrf
                        <button class="btn-sm" style="cursor: pointer;" type="submit">End schedule</button>
                    </form>
                </div>
            @endif
        @else
            <div style="color: var(--text-3); font-size: 13px; margin-top: 8px;">
                No Service Review schedule for this account. A new deal for {{ $customer->company }} can re-trigger the setup prompt.
            </div>
            @if ($canManage)
                <form method="POST" action="{{ route('deally.service-reviews.setup', $customer) }}" style="margin-top: 12px;">
                    @csrf
                    <button class="btn-sm primary" type="submit">Set up Service Reviews</button>
                </form>
            @endif
        @endif
    </div>

    <div class="settings-section">
        <div class="settings-title">Deals</div>
        <div class="settings-sub">{{ $deals->count() }} opportunities for {{ $customer->company }}</div>

        <table class="data-table">
            <thead>
                <tr><th>Customer</th><th>Contact</th><th>Package(s)</th><th>Stage</th><th style="text-align:right;">Value</th></tr>
            </thead>
            <tbody>
                @forelse ($deals as $opportunity)
                    <tr>
                        <td class="primary">
                            <a href="{{ route('deally.deals.show', $opportunity) }}" style="color: inherit; text-decoration: none;">{{ $opportunity->company }}</a>
                        </td>
                        <td>{{ $opportunity->contact_name ?: '—' }}{{ $opportunity->contact_title ? ' · '.$opportunity->contact_title : '' }}</td>
                        <td>{{ $opportunity->packages ?: '—' }}</td>
                        <td><span class="status-pill {{ $opportunity->stage }}">{{ ucfirst($opportunity->stage) }}</span></td>
                        <td class="mono" style="text-align:right;">{{ $opportunity->value ? '$'.number_format($opportunity->value) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center; color: var(--text-3);">No deals on this account yet.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($canManage)
            <span class="btn-sm primary" data-open-modal="modal-create-deal" style="cursor: pointer; margin-top: 10px;">＋ Create Deal</span>
        @endif
    </div>

    <div class="settings-section">
        <div class="settings-title">Calls</div>
        <div class="settings-sub">{{ $calls->count() }} recorded sessions · DeAlly transcribes and analyzes every call</div>

        <table class="data-table">
            <thead>
                <tr><th>Call</th><th>Agent</th><th>Date</th><th>Duration</th><th>Sentiment</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($calls as $call)
                    @php
                        $sentimentPill = match ($call->sentiment) {
                            'positive' => 'live',
                            'neutral' => 'pending',
                            default => 'rejected',
                        };
                    @endphp
                    <tr>
                        <td class="primary">{{ $call->name }}</td>
                        <td>{{ (int) $call->owner_user_id === (int) auth()->id() ? 'You' : ($ownerNames[$call->owner_user_id] ?? '—') }}</td>
                        <td class="mono">{{ $call->date->format('M d, Y') }}</td>
                        <td class="mono">{{ $call->duration ?: '—' }}</td>
                        <td><span class="status-pill {{ $sentimentPill }}">{{ ucfirst($call->sentiment) }}</span></td>
                        <td style="text-align:right;"><a href="{{ route('deally.calls.show', $call) }}" class="row-action primary">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center; color: var(--text-3);">No calls recorded for this account.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="settings-section">
        <div class="settings-title">Tasks</div>
        <div class="settings-sub">{{ $tasks->count() }} open and closed follow-ups</div>

        <table class="data-table">
            <thead>
                <tr><th>Task</th><th>Assigned To</th><th>Due</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($tasks as $task)
                    @php
                        $isClosed = $task->status === 'closed';
                        $isOverdue = ! $isClosed && $task->due_at !== null && $task->due_at->isPast();
                        $statusLabel = $isClosed ? 'Closed' : ($isOverdue ? 'Overdue' : 'To Do');
                        $statusPill = $isClosed ? 'closed' : ($isOverdue ? 'rejected' : 'pending');
                    @endphp
                    <tr>
                        <td class="primary">{{ $task->title }}</td>
                        <td>{{ $task->assignee ?: '—' }}</td>
                        <td class="mono">{{ $task->due_at ? $task->due_at->format('M d, g:ia') : '—' }}</td>
                        <td><span class="status-pill {{ $statusPill }}">{{ $statusLabel }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="text-align:center; color: var(--text-3);">No follow-ups on this account.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@if ($canManage)
@push('modals')
<div class="modal-overlay" id="modal-edit-customer">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon call">🏢</div>
            <div class="modal-header-body"><div class="modal-title">Edit {{ $customer->company }}</div><div class="modal-subtitle">Update the account or hand it off to another agent</div></div>
            <button class="modal-close" data-close-modal>✕</button>
        </div>
        <form method="POST" action="{{ route('deally.customers.update', $customer) }}">
            @csrf
            @method('PATCH')
            <div class="modal-body">
                <div class="field-block">
                    <div class="field-label">Contact</div>
                    <input class="input-field" name="contact_name" value="{{ $customer->contact_name }}" placeholder="Jane Doe">
                </div>
                <div class="field-block">
                    <div class="field-label">Contact title</div>
                    <input class="input-field" name="contact_title" value="{{ $customer->contact_title }}" placeholder="CTO">
                </div>
                @if ($ownerOptions->isNotEmpty())
                    <div class="field-block">
                        <div class="field-label">Owner</div>
                        <select class="input-field" name="owner_user_id">
                            @foreach ($ownerOptions as $ownerId => $ownerName)
                                <option value="{{ $ownerId }}" @selected((int) $ownerId === (int) $customer->owner_user_id)>
                                    {{ $ownerName }}@if ((int) $ownerId === (int) $customer->owner_user_id) (current owner)@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="field-block" style="color: var(--text-3); font-size: 13px;">
                        You are not in a team with assignable agents, so ownership can't be handed off from here.
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button class="btn-sm" type="button" data-close-modal>Cancel</button>
                <button class="btn-sm primary" type="submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modal-add-contact">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon call">🧑‍💼</div>
            <div class="modal-header-body"><div class="modal-title">Add a contact</div><div class="modal-subtitle">A new contact joins the existing {{ $customer->company }} account</div></div>
            <button class="modal-close" data-close-modal>✕</button>
        </div>
        <form method="POST" action="{{ route('deally.contacts.store', $customer) }}">
            @csrf
            <div class="modal-body">
                <div class="field-block">
                    <div class="field-label">Name <span style="color: var(--danger-bright);">*</span></div>
                    <input class="input-field" name="name" placeholder="Jane Doe" required>
                </div>
                <div class="field-block">
                    <div class="field-label">Title</div>
                    <input class="input-field" name="title" placeholder="COO">
                </div>
                <div class="field-block">
                    <div class="field-label">Email</div>
                    <input class="input-field" type="email" name="email" placeholder="jane@example.com">
                </div>
                <div class="field-block">
                    <div class="field-label">Phone</div>
                    <input class="input-field" name="phone" placeholder="+1 555 000 0000">
                </div>
                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-2); margin-top: 4px;">
                    <input type="checkbox" name="is_primary" value="1"> Primary contact
                </label>
            </div>
            <div class="modal-footer">
                <button class="btn-sm" type="button" data-close-modal>Cancel</button>
                <button class="btn-sm primary" type="submit">Add Contact</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modal-create-deal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon call">📊</div>
            <div class="modal-header-body"><div class="modal-title">New deal</div><div class="modal-subtitle">Attach a new deal to {{ $customer->company }}</div></div>
            <button class="modal-close" data-close-modal>✕</button>
        </div>
        <form method="POST" action="{{ route('deally.pipeline.store') }}">
            @csrf
            <input type="hidden" name="customer_id" value="{{ $customer->getKey() }}">
            <div class="modal-body">
                <div class="field-block">
                    <div class="field-label">Customer</div>
                    <input class="input-field" value="{{ $customer->company }}" disabled>
                </div>
                <div class="field-block">
                    <div class="field-label">Contact</div>
                    <input class="input-field" name="contact_name" placeholder="Jane Doe" value="{{ $customer->contact_name }}">
                </div>
                <div class="field-block">
                    <div class="field-label">Package(s)</div>
                    <input class="input-field" name="packages" placeholder="Enterprise Suite (3)">
                </div>
                <div class="field-block">
                    <div class="field-label">Stage</div>
                    <select class="input-field" name="stage">
                        @foreach (\Deally\Pipeline\Models\Opportunity::stages() as $stage)
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

@if ($schedule !== null)
<div class="modal-overlay" id="modal-sr-cadence">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon call">🔁</div>
            <div class="modal-header-body"><div class="modal-title">Edit cadence</div><div class="modal-subtitle">Change how often Service Reviews occur</div></div>
            <button class="modal-close" data-close-modal>✕</button>
        </div>
        <form method="POST" action="{{ route('deally.service-reviews.cadence', $schedule) }}">
            @csrf
            @method('PATCH')
            <div class="modal-body">
                <div class="field-block">
                    <div class="field-label">Cadence (every N days)</div>
                    <input class="input-field" type="number" name="cadence_days" min="1" max="365" value="{{ $schedule->cadence_days }}" required>
                </div>
                <div class="field-block">
                    <div class="field-label">Upcoming sessions</div>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-2); margin-bottom: 6px;">
                        <input type="radio" name="mode" value="future" checked> Apply to future sessions only
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-2);">
                        <input type="radio" name="mode" value="regenerate"> Regenerate all upcoming
                    </label>
                    <div style="font-size: 12px; color: var(--text-3); margin-top: 6px;">
                        Regenerating drops every upcoming slot and restarts the series from today at the new cadence.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-sm" type="button" data-close-modal>Cancel</button>
                <button class="btn-sm primary" type="submit">Save Cadence</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modal-sr-catchup">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon call">📅</div>
            <div class="modal-header-body"><div class="modal-title">Schedule a catch-up review</div><div class="modal-subtitle">Recover a missed Service Review session</div></div>
            <button class="modal-close" data-close-modal>✕</button>
        </div>
        <form method="POST" action="{{ route('deally.service-reviews.sessions.store', $schedule) }}">
            @csrf
            <div class="modal-body">
                <div class="field-block">
                    <div class="field-label">Date and time</div>
                    <input class="input-field" type="datetime-local" name="scheduled_at" required>
                </div>
                <div class="field-block">
                    <div class="field-label">Notes</div>
                    <input class="input-field" name="notes" placeholder="Catch-up review">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-sm" type="button" data-close-modal>Cancel</button>
                <button class="btn-sm primary" type="submit">Schedule</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modal-sr-reschedule">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon call">🕐</div>
            <div class="modal-header-body"><div class="modal-title">Reschedule review</div><div class="modal-subtitle">Pick a new date and time</div></div>
            <button class="modal-close" data-close-modal>✕</button>
        </div>
        <form method="POST" id="sr-reschedule-form" action="">
            @csrf
            @method('PATCH')
            <div class="modal-body">
                <div class="field-block">
                    <div class="field-label">Date and time</div>
                    <input class="input-field" type="datetime-local" name="scheduled_at" required>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-sm" type="button" data-close-modal>Cancel</button>
                <button class="btn-sm primary" type="submit">Save</button>
            </div>
        </form>
    </div>
</div>
@endif
@endpush

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Pre-fill and retarget the reschedule form from the trigger row.
        var rescheduleForm = document.getElementById('sr-reschedule-form');
        if (rescheduleForm) {
            document.querySelectorAll('[data-open-modal="modal-sr-reschedule"]').forEach(function (trigger) {
                trigger.addEventListener('click', function () {
                    rescheduleForm.setAttribute('action', trigger.getAttribute('data-session-url'));
                    var input = rescheduleForm.querySelector('input[name="scheduled_at"]');
                    var time = trigger.getAttribute('data-session-time');
                    if (input && time) input.value = time;
                });
            });
        }
    });
</script>
@endif