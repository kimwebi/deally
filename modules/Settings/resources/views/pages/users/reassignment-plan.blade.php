@extends('core::layouts.app', [
    'pageTitle' => 'Deactivate '.$membership->user->name,
    'pageSub' => 'Reassign their customers before removal',
])

@section('content')
<div class="list-page" style="max-width: 1020px;">
    <div class="list-header">
        <div>
            <div class="list-title">Reassignment plan · {{ $membership->user->name }}</div>
            <div class="list-subtitle">
                {{ $plan->count() }} customer(s) owned by {{ $membership->user->name }} · {{ $unassignedCount }} unassigned
            </div>
        </div>
        <a href="{{ route('deally.users.index') }}" class="btn-sm">← Users</a>
    </div>

    <p style="font-size: 13px; color: var(--text-2); max-width: 760px;">
        Deals inherit ownership from their customer, so approving this plan moves every deal, call, proposal and open task
        for these accounts automatically. Only a departing sales agent triggers a plan — team leader and solutions lead
        seats are never cascaded.
    </p>

    @if ($unassignedCount > 0)
        <div class="risk-panel" style="border-color: rgba(232, 162, 43, 0.3); background: rgba(232, 162, 43, 0.06);">
            <div class="report-panel-title">⚠ {{ $unassignedCount }} customer(s) still need an owner</div>
            <div class="risk-list">
                <div class="risk-item">Assign an owner to every customer before the plan can be approved.</div>
            </div>
        </div>
    @endif

    <div class="settings-section">
        <div class="settings-title">Plan</div>
        <div class="settings-sub">
            Suggested owners are the least-loaded sales agents in {{ $membership->user->name }}'s team
        </div>

        <form method="POST" action="{{ route('deally.users.reassignment.assign-selected', $membership) }}">
            @csrf
            <table class="data-table">
                <thead>
                    <tr><th></th><th>Customer</th><th>Suggested Owner</th><th>Owner</th><th>Urgency</th><th>Demand</th><th>Service Review</th></tr>
                </thead>
                <tbody>
                    @forelse ($plan as $row)
                        @php $customer = $row['customer']; @endphp
                        <tr>
                            <td>
                                <input type="checkbox" name="customer_ids[]" value="{{ $customer->getKey() }}">
                            </td>
                            <td class="primary">{{ $customer->company }}</td>
                            <td>{{ $row['suggested_owner_name'] ?? '—' }}</td>
                            <td>
                                @if ($agentOptions->isNotEmpty())
                                    <select name="owner_{{ $customer->getKey() }}" class="plan-owner-select input-field" style="font-size: 12px; padding: 4px 8px; max-width: 170px;">
                                        <option value="" @selected($row['owner_id'] === null)>—</option>
                                        @foreach ($agentOptions as $agentId => $agentName)
                                            <option value="{{ $agentId }}" @selected((int) $row['owner_id'] === (int) $agentId)>{{ $agentName }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <span style="color: var(--text-3); font-size: 12px;">No agents available</span>
                                @endif
                            </td>
                            <td><span class="risk-badge {{ $row['tier'] }}">{{ $row['urgency'] }}</span></td>
                            <td>
                                @if ($row['demand_account'])
                                    <span class="status-pill negotiation">Demand</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($row['service_review'] === 'active')
                                    <span class="status-pill live">Active</span>
                                @else
                                    <span class="status-pill closed">None</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="text-align:center; color: var(--text-3);">This agent owns no customer accounts.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div style="display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; align-items: center;">
                <button class="btn-sm primary" type="submit" @disabled($plan->isEmpty() || $agentOptions->isEmpty())>Assign Selected To…</button>
                <select class="input-field" name="owner_user_id" style="width: 180px;">
                    @foreach ($agentOptions as $agentId => $agentName)
                        <option value="{{ $agentId }}">{{ $agentName }}</option>
                    @endforeach
                </select>

                <span style="flex: 1;"></span>

                <button class="btn-sm" type="submit" formaction="{{ route('deally.users.reassignment.recalculate', $membership) }}" formmethod="POST">Recalculate</button>
            </div>
        </form>
    </div>

    <div class="settings-section">
        <div class="settings-title">Approve plan</div>
        <div class="settings-sub">
            Approval transfers ownership and removes {{ $membership->user->name }} from the tenant
        </div>

        <div style="display: flex; gap: 10px; margin-top: 12px; align-items: center;">
            <form method="POST" action="{{ route('deally.users.reassignment.approve', $membership) }}" style="display: inline;"
                  onsubmit="return confirm('Approve this plan? {{ $membership->user->name }} will be removed and their customers transferred.');">
                @csrf
                <button class="btn-sm primary" type="submit" @disabled(! $canApprove || $plan->isEmpty())>Approve Plan</button>
            </form>
            @if (! $canApprove && $plan->isNotEmpty())
                <span style="font-size: 12px; color: var(--text-3);">Assign every customer an owner to enable approval.</span>
            @endif
        </div>
    </div>
</div>
@endsection

@push('modals')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Per-row owner override posts immediately on change.
        document.querySelectorAll('.plan-owner-select').forEach(function (select) {
            select.addEventListener('change', function () {
                var ownerId = select.value;
                if (!ownerId) return;

                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("deally.users.reassignment.owner", $membership) }}';
                form.style.display = 'none';

                var token = document.createElement('input');
                token.type = 'hidden';
                token.name = '_token';
                token.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                form.appendChild(token);

                var customer = document.createElement('input');
                customer.type = 'hidden';
                customer.name = 'customer_id';
                customer.value = select.name.replace('owner_', '');
                form.appendChild(customer);

                var owner = document.createElement('input');
                owner.type = 'hidden';
                owner.name = 'owner_user_id';
                owner.value = ownerId;
                form.appendChild(owner);

                document.body.appendChild(form);
                form.submit();
            });
        });
    });
</script>
@endpush