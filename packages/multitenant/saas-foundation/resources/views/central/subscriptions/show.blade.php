@extends('layouts.app')
@php $pageTitle = 'Subscription Details'; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('central.subscriptions.index') }}">Subscriptions</a>
    <span>/</span>
    <span>Details</span>
</div>

<div class="page-header">
    <h1>Subscription Details</h1>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3>Overview</h3></div>
        <div class="card-body">
            <div class="list-group">
                <div class="list-group-item">
                    <span class="text-muted">Tenant</span>
                    <span><a href="{{ route('central.tenants.show', $tenant) }}">{{ $tenant->name }}</a></span>
                </div>
                <div class="list-group-item">
                    <span class="text-muted">Plan</span>
                    <span>{{ $subscription->plan->name }} ({{ $subscription->plan->price }} {{ $subscription->plan->currency }}/{{ $subscription->plan->billing_interval }})</span>
                </div>
                <div class="list-group-item">
                    <span class="text-muted">Status</span>
                    <span class="badge {{ $subscription->status === 'active' ? 'badge-success' : 'badge-gray' }}">{{ ucfirst($subscription->status) }}</span>
                </div>
                <div class="list-group-item">
                    <span class="text-muted">Starts at</span>
                    <span>{{ $subscription->starts_at?->format('M j, Y') ?? '—' }}</span>
                </div>
                <div class="list-group-item">
                    <span class="text-muted">Trial ends</span>
                    <span>{{ $subscription->trial_ends_at?->format('M j, Y') ?? '—' }}</span>
                </div>
                <div class="list-group-item">
                    <span class="text-muted">Ends at</span>
                    <span>{{ $subscription->ends_at?->format('M j, Y') ?? '—' }}</span>
                </div>
                <div class="list-group-item">
                    <span class="text-muted">Cancelled at</span>
                    <span>{{ $subscription->cancelled_at?->format('M j, Y') ?? '—' }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Entitlements</h3></div>
        <div class="card-body" style="padding:0;">
            @forelse($subscription->items as $item)
            <div class="list-group-item">
                <div>
                    <div style="font-weight:500;">{{ $item->feature->name }}</div>
                    <div class="text-muted text-sm">{{ $item->feature->group_name }}</div>
                </div>
                <div>
                    <span class="badge {{ $item->is_active ? 'badge-success' : 'badge-gray' }}">{{ $item->is_active ? 'Active' : 'Inactive' }}</span>
                    <span class="text-muted text-sm" style="margin-left:8px;">{{ $item->quantity }}</span>
                </div>
            </div>
            @empty
            <div class="text-center text-muted" style="padding:24px;">No entitlements.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection