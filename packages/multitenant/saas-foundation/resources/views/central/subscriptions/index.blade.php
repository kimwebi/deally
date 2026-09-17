@extends('layouts.app')
@php $pageTitle = 'Subscriptions'; @endphp

@section('content')
<div class="page-header">
    <h1>Subscriptions</h1>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('central.subscriptions.index') }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:end;">
            <div class="form-group" style="margin-bottom:0; flex:1; min-width:200px;">
                <label class="form-label">Search tenant</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control">
            </div>
            <div class="form-group" style="margin-bottom:0; min-width:140px;">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">All</option>
                    @foreach(['active','trialing','past_due','cancelled','expired','paused'] as $status)
                    <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-secondary">Filter</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Tenant</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Start</th>
                        <th>Trial ends</th>
                        <th>End</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subscriptions as $subscription)
                    <tr>
                        <td>{{ $subscription->tenant->name ?? 'Deleted' }}</td>
                        <td>{{ $subscription->plan->name }}</td>
                        <td>
                            @php
                            $badgeClass = match($subscription->status) {
                                'active' => 'badge-success',
                                'trialing' => 'badge-primary',
                                'past_due' => 'badge-warning',
                                'cancelled', 'expired', 'paused' => 'badge-gray',
                                default => 'badge-gray',
                            };
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ ucfirst($subscription->status) }}</span>
                        </td>
                        <td class="text-muted text-sm">{{ $subscription->starts_at?->format('Y-m-d') ?? '—' }}</td>
                        <td class="text-muted text-sm">{{ $subscription->trial_ends_at?->format('Y-m-d') ?? '—' }}</td>
                        <td class="text-muted text-sm">{{ $subscription->ends_at?->format('Y-m-d') ?? '—' }}</td>
                        <td class="text-right">
                            <a href="{{ route('central.subscriptions.show', $subscription) }}" class="btn btn-secondary btn-sm">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted" style="padding:32px;">No subscriptions found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($subscriptions->hasPages())
    <div class="card-footer">{{ $subscriptions->links() }}</div>
    @endif
</div>
@endsection