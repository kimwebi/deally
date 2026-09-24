@extends('core::layouts.app', [
    'pageTitle' => 'Customers',
    'pageSub' => $customers->count().' customer accounts · visible within your seat',
])

@section('content')
<div class="list-page">
    <div class="list-header">
        <div>
            <div class="list-title">Customers</div>
            <div class="list-subtitle">{{ $customers->count() }} customer accounts · ownership scoped to your seat</div>
        </div>
        @if ($canManage)
            <span class="btn-sm primary" data-open-modal="modal-add-customer" style="cursor: pointer;">＋ Add Customer</span>
        @endif
    </div>

    <table class="data-table">
        <thead>
            <tr><th>Customer</th><th>Contact</th><th>Owner</th><th>Team</th><th style="text-align:right;">Open deals</th><th style="text-align:right;">Open value</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($customers as $customer)
                <tr>
                    <td class="primary">{{ $customer->company }}</td>
                    <td>{{ $customer->contact_name ?: '—' }}{{ $customer->contact_title ? ' · '.$customer->contact_title : '' }}</td>
                    <td>
                        @if ((int) $customer->owner_user_id === (int) auth()->id())
                            <strong>You</strong>
                        @else
                            {{ $ownerNames[$customer->owner_user_id] ?? '—' }}
                        @endif
                    </td>
                    <td>{{ $teamNames[$customer->team_id] ?? '—' }}</td>
                    <td class="mono" style="text-align:right;">{{ $customer->open_deals_count }}</td>
                    <td class="mono" style="text-align:right;">{{ $customer->open_value ? '$'.number_format($customer->open_value) : '—' }}</td>
                    <td style="text-align:right;"><a href="{{ route('deally.customers.show', $customer) }}" class="row-action primary">View</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center; color: var(--text-3);">
                        No customer accounts in your seat yet.
                        @if ($canManage)
                            Add one to start owning the relationship.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

@if ($canManage)
@push('modals')
<div class="modal-overlay" id="modal-add-customer">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon call">🏢</div>
            <div class="modal-header-body"><div class="modal-title">Add Customer</div><div class="modal-subtitle">You become the account owner</div></div>
            <button class="modal-close" data-close-modal>✕</button>
        </div>
        <form method="POST" action="{{ route('deally.customers.store') }}">
            @csrf
            <div class="modal-body">
                <div class="field-block">
                    <div class="field-label">Company</div>
                    <input class="input-field" name="company" placeholder="Acme Corp" required>
                </div>
                <div class="field-block">
                    <div class="field-label">Contact</div>
                    <input class="input-field" name="contact_name" placeholder="Jane Doe">
                </div>
                <div class="field-block">
                    <div class="field-label">Contact title</div>
                    <input class="input-field" name="contact_title" placeholder="CTO">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-sm" type="button" data-close-modal>Cancel</button>
                <button class="btn-sm primary" type="submit">Add Customer</button>
            </div>
        </form>
    </div>
</div>
@endpush
@endif